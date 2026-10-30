<?php

use App\Events\SessionCanceled;
use App\Events\SessionReminder;
use App\Events\SessionRescheduled;
use App\Actions\CreateSessionAction;
use App\Models\Cohort;
use App\Models\MentorshipRelationship;
use App\Models\Notification;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\SessionParticipantExclusion;
use App\Models\User;
use App\Services\Scheduling\SessionBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class)->group('scheduling', 'notifications', 'change-events');

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-13 09:00:00', 'UTC'));
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-13 09:00:00', 'UTC'));
});

afterEach(function (): void {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

function changeNotificationFixture(array $overrides = []): array
{
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create(['locale' => 'es']);
    $responsible = User::factory()->create();
    $excluded = User::factory()->create();
    $left = User::factory()->create();
    $cohort = Cohort::create([
        'created_by' => $responsible->id,
        'name' => 'Change notification cohort',
        'status' => 'active',
    ]);
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'grupal',
        'source_type' => 'cohort',
        'source_id' => $cohort->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);
    $overrides['scheduled_at'] = Carbon::parse(
        $overrides['scheduled_at'] ?? '2026-10-15 15:00:00',
        'UTC',
    )->setTimezone((string) config('app.timezone'));
    $session = Session::factory()->create(array_merge([
        'relationship_id' => $relationship->id,
        'session_type' => 'group',
        'title' => 'Current group session',
        'scheduled_at' => Carbon::parse('2026-10-15 15:00:00', 'UTC'),
        'duration_minutes' => 60,
        'timezone' => 'Europe/Madrid',
        'status' => 'scheduled',
        'meet_link' => 'https://meet.example/current-session',
    ], $overrides));

    foreach ([$mentor, $member] as $attendee) {
        SessionAttendee::create(['session_id' => $session->id, 'user_id' => $attendee->id]);
    }
    SessionAttendee::create(['session_id' => $session->id, 'user_id' => $excluded->id]);
    SessionAttendee::create([
        'session_id' => $session->id,
        'user_id' => $left->id,
        'left_at' => Carbon::parse('2026-10-13 08:00:00', 'UTC')
            ->setTimezone((string) config('app.timezone')),
    ]);
    SessionParticipantExclusion::create([
        'session_id' => $session->id,
        'user_id' => $excluded->id,
        'excluded_at' => Carbon::parse('2026-10-12 12:00:00', 'UTC'),
    ]);
    return [$session, $mentor, $member, $responsible, $excluded, $left];
}

it('sends one change notification per current participant and collective responsible, using event snapshots', function (): void {
    Event::fake([SessionReminder::class]);
    [$session, $mentor, $member, $responsible, $excluded, $left] = changeNotificationFixture();

    $event = new SessionRescheduled(
        eventId: 'schedule-change-1',
        sessionId: $session->id,
        occurredAt: '2026-10-13T09:00:00+00:00',
        previousSchedule: [
            'scheduled_at' => '2026-10-13T11:00:00+00:00',
            'timezone' => 'America/Mexico_City',
            'duration_minutes' => 60,
        ],
        newSchedule: [
            'scheduled_at' => '2026-10-15T15:00:00+00:00',
            'timezone' => 'Europe/Madrid',
            'duration_minutes' => 60,
            'reason' => 'Cambio de agenda',
        ],
    );

    Event::dispatch($event);
    Event::dispatch($event);

    $notifications = Notification::query()->where('type', 'session_change')->get();
    expect($notifications)->toHaveCount(3)
        ->and($notifications->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$mentor->id, $member->id, $responsible->id])->sort()->values()->all())
        ->and($notifications->pluck('status')->unique()->all())->toBe(['pending']);

    $memberNotification = $notifications->firstWhere('user_id', $member->id);
    expect($memberNotification->data['previous_schedule']['scheduled_at'])->toBe('2026-10-13T11:00:00+00:00')
        ->and($memberNotification->data['scheduled_at'])->toBe('2026-10-15T15:00:00+00:00')
        ->and($memberNotification->data['timezone'])->toBe('Europe/Madrid')
        ->and($memberNotification->data['message'])->toContain('15 octubre 2026')
        ->and($notifications->contains(fn (Notification $notification): bool => in_array(
            $notification->user_id,
            [$excluded->id, $left->id],
            true,
        )))->toBeFalse();
    $this->artisan('app:send-session-reminders')->assertExitCode(0);
    expect(Notification::query()->where('type', 'session_reminder')->exists())->toBeFalse();
    Event::assertDispatched(SessionReminder::class, 3);
});

it('sends a separate immediate reminder for a re-scheduled session less than 24 hours away', function (): void {
    Event::fake([SessionReminder::class]);
    [$session, $mentor, $member] = changeNotificationFixture([
        'scheduled_at' => Carbon::parse('2026-10-13 20:00:00', 'UTC'),
        'timezone' => 'America/Mexico_City',
    ]);

    Event::dispatch(new SessionRescheduled(
        eventId: 'schedule-change-imminent',
        sessionId: $session->id,
        occurredAt: '2026-10-13T09:00:00+00:00',
        previousSchedule: [
            'scheduled_at' => '2026-10-15T15:00:00+00:00',
            'timezone' => 'Europe/Madrid',
            'duration_minutes' => 60,
        ],
        newSchedule: [
            'scheduled_at' => '2026-10-13T20:00:00+00:00',
            'timezone' => 'America/Mexico_City',
            'duration_minutes' => 60,
        ],
    ));

    $immediate = Notification::query()->where('type', 'session_reminder')->get();
    expect($immediate)->toHaveCount(2)
        ->and($immediate->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$mentor->id, $member->id])->sort()->values()->all());

    $memberReminder = $immediate->firstWhere('user_id', $member->id);
    expect($memberReminder->data['hours_until'])->toBe('immediate')
        ->and($memberReminder->data['scheduled_at'])->toBe('2026-10-13T20:00:00+00:00')
        ->and($memberReminder->data['timezone'])->toBe('America/Mexico_City')
        ->and($memberReminder->data['meet_link'])->toBe('https://meet.example/current-session')
        ->and($memberReminder->data['message'])->toContain('14:00')
        ->and(Notification::query()->where('type', 'session_change')->count())->toBe(3);

    Event::assertDispatched(SessionReminder::class, 5);
});

it('sends one cancellation alert without exposing the historical Meet link and stops routine reminders', function (): void {
    Event::fake([SessionReminder::class]);
    [$session, $mentor, $member, $responsible] = changeNotificationFixture([
        'status' => 'canceled',
        'scheduled_at' => Carbon::parse('2026-10-13 11:00:00', 'UTC'),
    ]);

    $event = new SessionCanceled(
        eventId: 'cancel-event-1',
        sessionId: $session->id,
        actorId: $member->id,
        occurredAt: '2026-10-13T09:00:00+00:00',
        reason: 'Cambio solicitado',
        late: false,
        leadMinutes: 120,
    );
    Event::dispatch($event);
    Event::dispatch($event);

    $this->artisan('app:send-session-reminders')->assertExitCode(0);

    $notifications = Notification::query()->where('type', 'session_change')->get();
    expect($notifications)->toHaveCount(3)
        ->and($notifications->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$mentor->id, $member->id, $responsible->id])->sort()->values()->all())
        ->and($notifications->every(fn (Notification $notification): bool =>
            ! array_key_exists('meet_link', $notification->data)
            && $notification->data['reason'] === 'Cambio solicitado'
        ))->toBeTrue()
        ->and(Notification::query()->where('type', 'session_reminder')->exists())->toBeFalse()
        ->and($session->fresh()->meet_link)->toBe('https://meet.example/current-session');

    Event::assertDispatched(SessionReminder::class, 3);
});

it('retries failed change delivery on the same event without creating duplicate alerts', function (): void {
    [$session, $mentor, $member, $responsible] = changeNotificationFixture();
    $event = new SessionRescheduled(
        eventId: 'schedule-change-retry',
        sessionId: $session->id,
        occurredAt: '2026-10-13T09:00:00+00:00',
        previousSchedule: [
            'scheduled_at' => '2026-10-14T10:00:00+00:00',
            'timezone' => 'America/Mexico_City',
            'duration_minutes' => 60,
        ],
        newSchedule: [
            'scheduled_at' => '2026-10-15T15:00:00+00:00',
            'timezone' => 'Europe/Madrid',
            'duration_minutes' => 60,
        ],
    );
    Event::listen(SessionReminder::class, function (): never {
        throw new RuntimeException('Notification channel unavailable');
    });

    Event::dispatch($event);

    $failed = Notification::query()->where('type', 'session_change')->get();
    expect($failed)->toHaveCount(3)
        ->and($failed->pluck('status')->unique()->all())->toBe(['failed']);

    Event::forget(SessionReminder::class);
    Event::fake([SessionReminder::class]);
    Event::dispatch($event);
    Event::dispatch($event);

    $retried = Notification::query()->where('type', 'session_change')->get();
    expect($retried)->toHaveCount(3)
        ->and($retried->pluck('status')->unique()->all())->toBe(['pending'])
        ->and($retried->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$mentor->id, $member->id, $responsible->id])->sort()->values()->all());
    Event::assertDispatched(SessionReminder::class, 3);
});

it('does not send an immediate reminder to a participant already reminded for the current schedule', function (): void {
    Event::fake([SessionReminder::class]);
    [$session, $mentor, $member] = changeNotificationFixture([
        'scheduled_at' => Carbon::parse('2026-10-13 20:00:00', 'UTC'),
        'timezone' => 'America/Mexico_City',
    ]);
    Notification::query()->create([
        'user_id' => $member->id,
        'type' => 'session_reminder',
        'dedupe_key' => App\Services\Scheduling\Rules\ReminderRules::reminderKey(
            $session->id,
            $member->id,
            2,
            $session->scheduled_at,
        ),
        'status' => 'sent',
        'sent_at' => now(),
        'data' => ['session_id' => $session->id],
    ]);

    Event::dispatch(new SessionRescheduled(
        eventId: 'schedule-change-already-reminded',
        sessionId: $session->id,
        occurredAt: '2026-10-13T09:00:00+00:00',
        previousSchedule: [
            'scheduled_at' => '2026-10-15T15:00:00+00:00',
            'timezone' => 'Europe/Madrid',
            'duration_minutes' => 60,
        ],
        newSchedule: [
            'scheduled_at' => '2026-10-13T20:00:00+00:00',
            'timezone' => 'America/Mexico_City',
            'duration_minutes' => 60,
        ],
    ));

    $immediate = Notification::query()
        ->where('type', 'session_reminder')
        ->where('dedupe_key', 'like', 'session_reminder:%:immediate:%')
        ->get();
    expect($immediate)->toHaveCount(1)
        ->and($immediate->sole()->user_id)->toBe($mentor->id)
        ->and(Notification::query()->where('type', 'session_change')->count())->toBe(3);
});

it('sends an immediate reminder after creating a session less than 24 hours away', function (): void {
    Event::fake([SessionReminder::class]);
    [$session, $mentor, $member] = changeNotificationFixture([
        'scheduled_at' => Carbon::parse('2026-10-14 08:59:00', 'UTC'),
        'timezone' => 'Europe/Madrid',
    ]);
    $actor = User::factory()->create();
    $booking = Mockery::mock(SessionBookingService::class);
    $booking->shouldReceive('validateAndCreate')->once()->andReturn($session);
    app()->instance(SessionBookingService::class, $booking);

    app(CreateSessionAction::class)->execute(['relationship_id' => $session->relationship_id], $actor);

    $immediate = Notification::query()
        ->where('type', 'session_reminder')
        ->where('dedupe_key', 'like', 'session_reminder:%:immediate:%')
        ->get();
    expect($immediate)->toHaveCount(2)
        ->and($immediate->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$mentor->id, $member->id])->sort()->values()->all())
        ->and($immediate->every(fn (Notification $notification): bool =>
            $notification->status === 'pending'
            && $notification->data['hours_until'] === 'immediate'
            && $notification->data['scheduled_at'] === '2026-10-14T08:59:00+00:00'
        ))->toBeTrue();
    Event::assertDispatched(SessionReminder::class, 2);

    $this->artisan('app:send-session-reminders')->assertExitCode(0);
    expect(Notification::query()->where('type', 'session_reminder')->count())->toBe(2);
});

it('does not send the creation-immediate reminder at the exact 24-hour boundary', function (): void {
    Event::fake([SessionReminder::class]);
    [$session] = changeNotificationFixture([
        'scheduled_at' => Carbon::parse('2026-10-14 09:00:00', 'UTC'),
    ]);
    $booking = Mockery::mock(SessionBookingService::class);
    $booking->shouldReceive('validateAndCreate')->once()->andReturn($session);
    app()->instance(SessionBookingService::class, $booking);

    app(CreateSessionAction::class)->execute(['relationship_id' => $session->relationship_id], User::factory()->create());

    expect(Notification::query()->where('type', 'session_reminder')->count())->toBe(0);
    Event::assertNotDispatched(SessionReminder::class);
});
