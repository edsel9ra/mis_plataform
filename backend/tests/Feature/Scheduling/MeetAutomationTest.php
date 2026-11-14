<?php

use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;
use App\Events\SessionCanceled;
use App\Events\SessionRescheduled;
use App\Http\Resources\SessionResource;
use App\Listeners\SessionCanceledListener;
use App\Listeners\SessionRescheduledListener;
use App\Services\Scheduling\SessionNotificationService;
use App\Services\Scheduling\MeetAutomationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses()->group('scheduling', 'meet');

beforeEach(function (): void {
    CarbonImmutable::setTestNow(dbInstant('2026-10-13 09:00:00'));
    $this->credentialsPath = storage_path('app/google-service-account.json');
    $this->previousCredentials = file_exists($this->credentialsPath)
        ? file_get_contents($this->credentialsPath)
        : null;

    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    openssl_pkey_export($key, $privateKey);
    if (! is_dir(dirname($this->credentialsPath))) {
        mkdir(dirname($this->credentialsPath), 0775, true);
    }
    file_put_contents($this->credentialsPath, json_encode([
        'client_email' => 'meet-test@example.invalid',
        'private_key' => $privateKey,
    ], JSON_THROW_ON_ERROR));

    config([
        'services.google.client_id' => 'test-client',
        'services.google.client_secret' => 'test-secret',
        'services.google.calendar.impersonate' => 'calendar-test@example.invalid',
        'services.google.timeout' => 1,
    ]);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
    if ($this->previousCredentials === null) {
        @unlink($this->credentialsPath);
    } else {
        file_put_contents($this->credentialsPath, $this->previousCredentials);
    }
});

function meetAutomationRelationship(): MentorshipRelationship
{
    $mentee = User::factory()->create();

    return MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $mentee->id,
    ]);
}

function fakeGoogleMeetEvent(array $eventResponses = []): void
{
    $eventSequence = Http::sequence();
    foreach ($eventResponses ?: [[
        'id' => 'google-event-created',
        'hangoutLink' => 'https://meet.google.com/test-link',
    ]] as $response) {
        if (isset($response['__status'])) {
            $eventSequence->pushStatus($response['__status']);
        } else {
            $eventSequence->push($response);
        }
    }

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'temporary-test-token'], 200),
        'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => $eventSequence,
    ]);
}

it('creates Meet for future scheduled sessions in the session timezone without guests', function (): void {
    fakeGoogleMeetEvent();
    $relationship = meetAutomationRelationship();
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'title' => 'Session in Lisbon',
        'scheduled_at' => dbInstant('2026-10-13 13:00:00'),
        'timezone' => 'Europe/Lisbon',
        'duration_minutes' => 45,
        'status' => 'scheduled',
        'meet_link' => null,
        'meet_event_id' => null,
    ]);

    $this->artisan('app:generate-meet-links')->assertExitCode(0);

    expect($session->fresh()->meet_link)->toBe('https://meet.google.com/test-link')
        ->and($session->fresh()->meet_event_id)->toBe('google-event-created')
        ->and($session->fresh()->meet_attempts)->toBe(0)
        ->and($session->fresh()->status)->toBe('scheduled');

    Http::assertSent(fn (Request $request): bool =>
        str_contains($request->url(), '/events?')
        && $request['start']['dateTime'] === '2026-10-13T13:00:00+00:00'
        && $request['end']['dateTime'] === '2026-10-13T13:45:00+00:00'
        && $request['start']['timeZone'] === 'Europe/Lisbon'
        && $request['end']['timeZone'] === 'Europe/Lisbon'
        && ! array_key_exists('attendees', $request->data())
    );
});

it('returns an existing Meet link on repeated sweeps without creating a second event', function (): void {
    fakeGoogleMeetEvent();
    $relationship = meetAutomationRelationship();
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => dbInstant('2026-10-13 13:00:00'),
        'status' => 'scheduled',
        'meet_link' => null,
        'meet_event_id' => null,
    ]);

    $this->artisan('app:generate-meet-links')->assertExitCode(0);
    $eventId = $session->fresh()->meet_event_id;
    $meetLink = $session->fresh()->meet_link;
    $this->artisan('app:generate-meet-links')->assertExitCode(0);

    expect($session->fresh()->meet_event_id)->toBe($eventId)
        ->and($session->fresh()->meet_link)->toBe($meetLink);

    Http::assertSentCount(2); // OAuth token + exactly one Calendar create request.
});

it('returns the persisted event and link from ensure without calling Google again', function (): void {
    fakeGoogleMeetEvent();
    $relationship = meetAutomationRelationship();
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => dbInstant('2026-10-13 13:00:00'),
        'status' => 'scheduled',
        'meet_link' => 'https://meet.google.com/already-created',
        'meet_event_id' => 'already-created-event',
    ]);

    $result = app(MeetAutomationService::class)->ensure(
        $session,
        dbInstant('2026-10-13 09:00:00'),
    );

    expect($result)->toBe([
        'meet_link' => 'https://meet.google.com/already-created',
        'event_id' => 'already-created-event',
    ]);
    Http::assertNothingSent();
});

it('skips canceled, started, past, and already-associated sessions', function (): void {
    fakeGoogleMeetEvent();
    $relationship = meetAutomationRelationship();
    $start = dbInstant('2026-10-13 13:00:00');
    $canceled = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => $start->addHour(),
        'status' => 'canceled',
    ]);
    $inProgress = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => $start->addHours(2),
        'status' => 'in_progress',
    ]);
    $past = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => dbInstant('2026-10-13 08:59:59'),
        'status' => 'scheduled',
    ]);
    $hasEvent = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => $start->addHours(3),
        'status' => 'scheduled',
        'meet_event_id' => 'existing-event-without-link',
    ]);
    $hasEvent->forceFill([
        'meet_synced_schedule_hash' => \App\Services\Scheduling\Rules\MeetAutomationRules::scheduleFingerprint($hasEvent),
    ])->save();

    $this->artisan('app:generate-meet-links')->assertExitCode(0);

    expect($canceled->fresh()->meet_link)->toBeNull()
        ->and($inProgress->fresh()->meet_link)->toBeNull()
        ->and($past->fresh()->meet_link)->toBeNull()
        ->and($hasEvent->fresh()->meet_link)->toBeNull();
    Http::assertNothingSent();
});

it('records sanitized failure and retries without rolling back the scheduled session', function (): void {
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'temporary-test-token'], 200),
        'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::sequence()
            ->pushStatus(503)
            ->push([
                'id' => 'google-event-after-retry',
                'hangoutLink' => 'https://meet.google.com/retried-link',
            ]),
    ]);
    $relationship = meetAutomationRelationship();
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => dbInstant('2026-10-13 13:00:00'),
        'status' => 'scheduled',
        'meet_link' => null,
        'meet_event_id' => null,
    ]);

    $this->artisan('app:generate-meet-links')->assertExitCode(0);

    expect($session->fresh()->status)->toBe('scheduled')
        ->and($session->fresh()->meet_attempts)->toBe(1)
        ->and($session->fresh()->meet_last_attempt_at->equalTo(dbInstant('2026-10-13 09:00:00')))->toBeTrue()
        ->and($session->fresh()->meet_last_error)->not->toBeEmpty()
        ->and($session->fresh()->meet_last_error)->not->toContain('temporary-test-token');

    $this->artisan('app:generate-meet-links')->assertExitCode(0);

    expect($session->fresh()->status)->toBe('scheduled')
        ->and($session->fresh()->meet_link)->toBe('https://meet.google.com/retried-link')
        ->and($session->fresh()->meet_event_id)->toBe('google-event-after-retry')
        ->and($session->fresh()->meet_attempts)->toBe(0)
        ->and($session->fresh()->meet_last_error)->toBeNull();
});

it('recovers an event created remotely before the insert response is lost instead of inserting a duplicate', function (): void {
    $activeEvents = [];
    $insertAttempts = 0;
    $attemptedEventIds = [];
    $attemptedConferenceRequestIds = [];
    Http::fake(function (Request $request) use (&$activeEvents, &$insertAttempts, &$attemptedEventIds, &$attemptedConferenceRequestIds) {
        if ($request->url() === 'https://oauth2.googleapis.com/token') {
            return Http::response(['access_token' => 'temporary-test-token'], 200);
        }

        if ($request->method() === 'POST') {
            $insertAttempts++;
            $eventId = (string) $request['id'];
            $attemptedEventIds[] = $eventId;
            $attemptedConferenceRequestIds[] = $request['conferenceData']['createRequest']['requestId'];
            if (isset($activeEvents[$eventId])) {
                return Http::response(['error' => ['message' => 'duplicate event id']], 409);
            }

            $activeEvents[$eventId] = [
                'id' => $eventId,
                'hangoutLink' => 'https://meet.google.com/recovered-link',
                'start' => $request['start'],
                'end' => $request['end'],
            ];
            throw new RuntimeException('Google created the event but the response was lost');
        }

        if ($request->method() === 'PATCH') {
            $eventId = basename(parse_url($request->url(), PHP_URL_PATH));
            $activeEvents[$eventId] = array_merge($activeEvents[$eventId], $request->data());

            return Http::response($activeEvents[$eventId], 200);
        }

        if ($request->method() === 'GET') {
            $eventId = basename(parse_url($request->url(), PHP_URL_PATH));

            return isset($activeEvents[$eventId])
                ? Http::response($activeEvents[$eventId], 200)
                : Http::response([], 404);
        }

        return Http::response([], 500);
    });
    $relationship = meetAutomationRelationship();
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => dbInstant('2026-10-13 13:00:00'),
        'timezone' => 'Europe/Lisbon',
        'status' => 'scheduled',
        'meet_link' => null,
        'meet_event_id' => null,
    ]);

    $this->artisan('app:generate-meet-links')->assertExitCode(0);
    expect($session->fresh()->meet_event_id)->toBeNull()
        ->and($session->fresh()->meet_attempts)->toBe(1)
        ->and($activeEvents)->toHaveCount(1);

    $this->artisan('app:generate-meet-links')->assertExitCode(0);

    $remoteId = array_key_first($activeEvents);
    $createRequests = Http::recorded()
        ->map(fn (array $pair) => $pair[0])
        ->filter(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/events?'))
        ->values();
    expect($session->fresh()->meet_event_id)->toBe($remoteId)
        ->and($session->fresh()->meet_link)->toBe('https://meet.google.com/recovered-link')
        ->and($activeEvents)->toHaveCount(1)
        ->and($insertAttempts)->toBe(2)
        ->and($attemptedEventIds)->toBe([$remoteId, $remoteId])
        ->and($attemptedConferenceRequestIds[0])->not->toBe($attemptedConferenceRequestIds[1])
        ->and($createRequests)->toHaveCount(1)
        ->and($createRequests->pluck('id')->unique()->all())->toBe([$remoteId])
        ->and($remoteId)->toMatch('/^[0-9a-v]{5,1024}$/');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_ends_with($request->url(), '/events/'.$remoteId));
});

it('updates the existing event after rescheduling with the session timezone and no guests', function (): void {
    $activeEvents = ['google-event-to-update' => ['id' => 'google-event-to-update']];
    Http::fake(function (Request $request) use (&$activeEvents) {
        if ($request->url() === 'https://oauth2.googleapis.com/token') {
            return Http::response(['access_token' => 'temporary-test-token'], 200);
        }

        if ($request->method() === 'PATCH') {
            $eventId = basename(parse_url($request->url(), PHP_URL_PATH));
            $activeEvents[$eventId] = array_merge($activeEvents[$eventId], $request->data(), [
                'hangoutLink' => 'https://meet.google.com/updated-link',
            ]);

            return Http::response($activeEvents[$eventId], 200);
        }

        return Http::response([], 500);
    });
    $relationship = meetAutomationRelationship();
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'title' => 'Reprogrammed session',
        'scheduled_at' => dbInstant('2026-10-14 16:30:00'),
        'timezone' => 'America/Mexico_City',
        'duration_minutes' => 75,
        'status' => 'scheduled',
        'meet_link' => 'https://meet.google.com/old-link',
        'meet_event_id' => 'google-event-to-update',
    ]);
    $notifications = Mockery::mock(SessionNotificationService::class);
    $notifications->shouldReceive('handleRescheduled')->twice();
    $event = new SessionRescheduled(
        eventId: 'stable-reschedule-event',
        sessionId: $session->id,
        occurredAt: '2026-10-13T09:00:00+00:00',
        previousSchedule: [
            'scheduled_at' => '2026-10-14T15:00:00+00:00',
            'timezone' => 'Europe/Lisbon',
            'duration_minutes' => 60,
        ],
        newSchedule: [
            'scheduled_at' => '2026-10-14T16:30:00+00:00',
            'timezone' => 'America/Mexico_City',
            'duration_minutes' => 75,
        ],
    );
    $listener = new SessionRescheduledListener($notifications, app(MeetAutomationService::class));

    $listener->handle($event);
    $listener->handle($event);

    $updatedRequest = Http::recorded()
        ->map(fn (array $pair) => $pair[0])
        ->first(fn (Request $request) => $request->method() === 'PATCH');

    expect($activeEvents)->toHaveCount(1)
        ->and($session->fresh()->meet_event_id)->toBe('google-event-to-update')
        ->and($session->fresh()->meet_link)->toBe('https://meet.google.com/updated-link')
        ->and($updatedRequest)->not->toBeNull()
        ->and($updatedRequest['start']['timeZone'])->toBe('America/Mexico_City')
        ->and($updatedRequest['end']['timeZone'])->toBe('America/Mexico_City')
        ->and($updatedRequest['end']['dateTime'])->toBe('2026-10-14T17:45:00+00:00')
        ->and(array_key_exists('attendees', $updatedRequest->data()))->toBeFalse();
    Http::assertSentCount(3); // OAuth token + same event updated idempotently twice.
});

it('retries a failed reschedule update on the scheduled sweep using the latest session schedule', function (): void {
    $activeEvents = [
        'google-event-retry-update' => [
            'id' => 'google-event-retry-update',
            'start' => ['dateTime' => '2026-10-14T10:00:00+00:00', 'timeZone' => 'Europe/Lisbon'],
            'end' => ['dateTime' => '2026-10-14T11:00:00+00:00', 'timeZone' => 'Europe/Lisbon'],
        ],
    ];
    $updateAttempts = 0;
    Http::fake(function (Request $request) use (&$activeEvents, &$updateAttempts) {
        if ($request->url() === 'https://oauth2.googleapis.com/token') {
            return Http::response(['access_token' => 'temporary-test-token'], 200);
        }

        if ($request->method() === 'PATCH') {
            $updateAttempts++;
            if ($updateAttempts === 1) {
                return Http::response(['error' => ['message' => 'temporary provider failure']], 503);
            }

            $eventId = basename(parse_url($request->url(), PHP_URL_PATH));
            $activeEvents[$eventId] = array_merge($activeEvents[$eventId], $request->data());

            return Http::response($activeEvents[$eventId], 200);
        }

        return Http::response([], 500);
    });
    $relationship = meetAutomationRelationship();
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => dbInstant('2026-10-14 16:30:00'),
        'timezone' => 'America/Mexico_City',
        'duration_minutes' => 75,
        'status' => 'scheduled',
        'meet_link' => 'https://meet.google.com/existing-link',
        'meet_event_id' => 'google-event-retry-update',
    ]);

    app(MeetAutomationService::class)->synchronizeRescheduled(
        $session->id,
        dbInstant('2026-10-13 09:00:00'),
    );

    expect($session->fresh()->meet_attempts)->toBe(1)
        ->and($session->fresh()->meet_last_error)->toBe('Google Meet update failed.')
        ->and($activeEvents['google-event-retry-update']['start']['dateTime'])->toBe('2026-10-14T10:00:00+00:00');

    $this->artisan('app:generate-meet-links')->assertExitCode(0);

    expect($session->fresh()->meet_attempts)->toBe(0)
        ->and($session->fresh()->meet_last_error)->toBeNull()
        ->and($activeEvents)->toHaveCount(1)
        ->and($activeEvents['google-event-retry-update']['start']['dateTime'])->toBe('2026-10-14T16:30:00+00:00')
        ->and($activeEvents['google-event-retry-update']['start']['timeZone'])->toBe('America/Mexico_City')
        ->and($activeEvents['google-event-retry-update']['end']['dateTime'])->toBe('2026-10-14T17:45:00+00:00')
        ->and($updateAttempts)->toBe(2);
});

it('recreates a missing remote event without retaining an active duplicate', function (): void {
    $activeEvents = [];
    Http::fake(function (Request $request) use (&$activeEvents) {
        if ($request->url() === 'https://oauth2.googleapis.com/token') {
            return Http::response(['access_token' => 'temporary-test-token'], 200);
        }

        if ($request->method() === 'PATCH') {
            return Http::response(['error' => ['message' => 'not found']], 404);
        }

        if ($request->method() === 'POST') {
            $activeEvents['replacement-event'] = [
                'id' => 'replacement-event',
                'hangoutLink' => 'https://meet.google.com/replacement-link',
            ];

            return Http::response($activeEvents['replacement-event'], 200);
        }

        return Http::response([], 500);
    });
    $relationship = meetAutomationRelationship();
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => dbInstant('2026-10-14 16:30:00'),
        'timezone' => 'America/Mexico_City',
        'status' => 'scheduled',
        'meet_link' => 'https://meet.google.com/old-link',
        'meet_event_id' => 'missing-event',
    ]);
    $notifications = Mockery::mock(SessionNotificationService::class);
    $notifications->shouldReceive('handleRescheduled')->once();

    (new SessionRescheduledListener($notifications, app(MeetAutomationService::class)))->handle(
        new SessionRescheduled(
            eventId: 'stale-event-reschedule',
            sessionId: $session->id,
            occurredAt: '2026-10-13T09:00:00+00:00',
            previousSchedule: [],
            newSchedule: [
                'scheduled_at' => '2026-10-14T16:30:00+00:00',
                'timezone' => 'America/Mexico_City',
                'duration_minutes' => 60,
            ],
        ),
    );

    expect($activeEvents)->toHaveCount(1)
        ->and($session->fresh()->meet_event_id)->toBe('replacement-event')
        ->and($session->fresh()->meet_link)->toBe('https://meet.google.com/replacement-link');
    Http::assertSent(fn (Request $request): bool =>
        $request->method() === 'POST'
        && str_contains($request->url(), '/events?')
        && $request['start']['timeZone'] === 'America/Mexico_City'
        && ! array_key_exists('attendees', $request->data())
    );
});

it('deletes a canceled session event best-effort and preserves its historical link on success', function (): void {
    $activeEvents = ['google-event-canceled' => true];
    Http::fake(function (Request $request) use (&$activeEvents) {
        if ($request->url() === 'https://oauth2.googleapis.com/token') {
            return Http::response(['access_token' => 'temporary-test-token'], 200);
        }

        if ($request->method() === 'DELETE') {
            unset($activeEvents['google-event-canceled']);

            return Http::response([], 204);
        }

        return Http::response([], 500);
    });
    $relationship = meetAutomationRelationship();
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'status' => 'canceled',
        'meet_link' => 'https://meet.google.com/historical-secret',
        'meet_event_id' => 'google-event-canceled',
    ]);
    $notifications = Mockery::mock(SessionNotificationService::class);
    $notifications->shouldReceive('handleCanceled')->twice();
    $event = new SessionCanceled(
        eventId: $session->id,
        sessionId: $session->id,
        actorId: 'actor-id',
        occurredAt: '2026-10-13T09:00:00+00:00',
        reason: 'Test cancellation',
        late: false,
        leadMinutes: 120,
    );
    $listener = new SessionCanceledListener($notifications, app(MeetAutomationService::class));

    $listener->handle($event);
    $listener->handle($event);

    expect($activeEvents)->toBeEmpty()
        ->and($session->fresh()->status)->toBe('canceled')
        ->and($session->fresh()->meet_link)->toBe('https://meet.google.com/historical-secret')
        ->and($session->fresh()->meet_event_id)->toBe('google-event-canceled')
        ->and((new SessionResource($session->fresh()))->resolve(HttpRequest::create('/'))['meet_link'])->toBeNull();
    $this->actingAs($relationship->source)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->assertJsonPath('meet_link', null)
        ->assertDontSee('historical-secret');
    Http::assertSentCount(3); // OAuth token + idempotent delete on initial delivery/replay.
});

it('records a safe Meet deletion failure without changing canceled state or exposing the link', function (): void {
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'temporary-test-token'], 200),
        'https://www.googleapis.com/calendar/v3/calendars/primary/events/*' => Http::response([
            'error' => ['message' => 'https://meet.google.com/historical-secret temporary-test-token'],
        ], 503),
    ]);
    Log::spy();
    $relationship = meetAutomationRelationship();
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'status' => 'canceled',
        'meet_link' => 'https://meet.google.com/historical-secret',
        'meet_event_id' => 'google-event-delete-fails',
    ]);
    $notifications = Mockery::mock(SessionNotificationService::class);
    $notifications->shouldReceive('handleCanceled')->once();

    (new SessionCanceledListener($notifications, app(MeetAutomationService::class)))->handle(
        new SessionCanceled(
            eventId: $session->id,
            sessionId: $session->id,
            actorId: 'actor-id',
            occurredAt: '2026-10-13T09:00:00+00:00',
            reason: 'Test cancellation',
            late: false,
            leadMinutes: 120,
        ),
    );

    expect($session->fresh()->status)->toBe('canceled')
        ->and($session->fresh()->meet_link)->toBe('https://meet.google.com/historical-secret')
        ->and($session->fresh()->meet_event_id)->toBe('google-event-delete-fails')
        ->and($session->fresh()->meet_attempts)->toBe(1)
        ->and($session->fresh()->meet_last_error)->toBe('Google Meet deletion failed.')
        ->and((new SessionResource($session->fresh()))->resolve(HttpRequest::create('/'))['meet_link'])->toBeNull();
    $this->actingAs($relationship->source)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->assertJsonPath('meet_link', null)
        ->assertDontSee('historical-secret');
    $this->postJson("/api/v1/sessions/{$session->id}/meet-link")
        ->assertOk()
        ->assertJsonPath('meet_link', null)
        ->assertDontSee('historical-secret');
    Log::shouldHaveReceived('warning')->with('scheduling.meet_delete_failed', Mockery::on(
        fn (array $context): bool => $context['session_id'] === $session->id
            && $context['attempts'] === 1
            && ! str_contains(serialize($context), 'historical-secret')
            && ! str_contains(serialize($context), 'temporary-test-token'),
    ))->once();
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE');
});
