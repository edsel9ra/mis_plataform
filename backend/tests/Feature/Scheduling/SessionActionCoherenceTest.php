<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\MentorAvailabilityWindow;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class)->group('scheduling', 'action-coherence');

/*
 * Coherence contract: `available_actions` from GET /sessions/{id} must agree
 * with what the real endpoints do — `allowed` means the action is not rejected
 * by permission or state, and a rejection after a state change surfaces the
 * domain error.
 */

/**
 * @return array{0: Session, 1: User, 2: array<string, mixed>}
 */
function coherenceScenario(string $actorKind, string $moment): array
{
    $mentor = User::factory()->mentor()->create();

    [$status, $offsetSeconds, $duration] = match ($moment) {
        'future_far' => ['scheduled', 10800, 60],
        'future_late' => ['scheduled', 3600, 60],
        'in_progress' => ['in_progress', -1800, 60],
        'ended_window' => ['in_progress', -7200, 60],
        'ended_closed' => ['in_progress', -180000, 60],
        'canceled' => ['canceled', 10800, 60],
        'completed' => ['completed', -7200, 60],
        'not_completed' => ['not_completed', -7200, 60],
        default => throw new InvalidArgumentException($moment),
    };

    $sessionOverrides = [
        'scheduled_at' => Carbon::now()->addSeconds($offsetSeconds),
        'duration_minutes' => $duration,
        'status' => $status,
    ];

    $buildSession = function (
        MentorshipRelationship $relationship,
        array $attendeeIds,
    ) use ($sessionOverrides): Session {
        $session = Session::factory()->create(array_merge($sessionOverrides, [
            'relationship_id' => $relationship->id,
            'session_type' => match ($relationship->type) {
                'familiar' => 'family',
                'grupal' => 'group',
                'empresa' => 'corporate',
                default => 'individual',
            },
        ]));
        foreach ($attendeeIds as $userId) {
            SessionAttendee::factory()->create([
                'session_id' => $session->id,
                'user_id' => $userId,
                'created_at' => CarbonImmutable::parse($session->getRawOriginal('scheduled_at'))->subDays(2),
            ]);
        }

        return $session->fresh();
    };

    $relationshipFor = function (string $type, string $sourceType, string $sourceId) use ($mentor): MentorshipRelationship {
        return MentorshipRelationship::factory()->create([
            'type' => $type,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'mentor_id' => $mentor->id,
        ]);
    };

    return match ($actorKind) {
        'mentor', 'client', 'unrelated', 'admin_support' => (function () use (
            $actorKind, $mentor, $relationshipFor, $buildSession,
        ): array {
            $client = User::factory()->create();
            $relationship = $relationshipFor('personal', 'user', $client->id);
            $session = $buildSession($relationship, [$client->id, $mentor->id]);

            $actor = match ($actorKind) {
                'mentor' => $mentor,
                'client' => $client,
                'unrelated' => User::factory()->create(),
                'admin_support' => (function (): User {
                    $admin = User::factory()->admin()->create();
                    $admin->assignRole(Role::findOrCreate('admin', 'web'));
                    $admin->givePermissionTo(
                        Permission::findOrCreate('scheduling.view.support', 'web'),
                        Permission::findOrCreate('scheduling.manage.support', 'web'),
                    );

                    return $admin;
                })(),
            };

            return [$session, $actor, ['client' => $client]];
        })(),
        'responsible_family', 'member_minor' => (function () use (
            $actorKind, $relationshipFor, $buildSession,
        ): array {
            $manager = User::factory()->create();
            $minor = User::factory()->create();
            $adult = User::factory()->create();
            $family = FamilyGroup::create(['head_user_id' => $manager->id, 'family_name' => 'Coherence family']);
            FamilyMember::create(['family_group_id' => $family->id, 'user_id' => $minor->id, 'full_name' => $minor->name, 'age' => 10, 'relationship' => 'child']);
            FamilyMember::create(['family_group_id' => $family->id, 'user_id' => $adult->id, 'full_name' => $adult->name, 'age' => 36, 'relationship' => 'parent']);

            $relationship = $relationshipFor('familiar', 'family_group', $family->id);
            $session = $buildSession($relationship, [$minor->id, $adult->id]);

            $actor = $actorKind === 'responsible_family' ? $manager : $minor;

            return [$session, $actor, ['minor' => $minor, 'adult' => $adult, 'manager' => $manager]];
        })(),
        'responsible_company' => (function () use ($relationshipFor, $buildSession): array {
            $manager = User::factory()->create();
            $employee = User::factory()->create();
            $nonEmployee = User::factory()->create();
            $company = Company::create(['legal_name' => 'Coherence Co', 'admin_id' => $manager->id, 'subscription_status' => 'trial']);
            Employee::create(['company_id' => $company->id, 'user_id' => $employee->id, 'status' => 'active']);

            $relationship = $relationshipFor('empresa', 'company', $company->id);
            $session = $buildSession($relationship, [$employee->id, $nonEmployee->id]);

            return [$session, $manager, ['employee' => $employee, 'non_employee' => $nonEmployee]];
        })(),
        default => throw new InvalidArgumentException($actorKind),
    };
}

function coherenceRescheduleTarget(Session $session): Carbon
{
    return Carbon::now()->addDays(30)->setTime(10, 0);
}

function coherenceReschedulePayload(Session $session): array
{
    return [
        'scheduled_at' => coherenceRescheduleTarget($session)->toIso8601String(),
        'duration_minutes' => (int) $session->duration_minutes,
        'timezone' => $session->timezone,
    ];
}

function coherenceRescheduleWindow(Session $session): void
{
    $target = coherenceRescheduleTarget($session)->setTimezone($session->timezone);
    MentorAvailabilityWindow::factory()
        ->for($session->relationship->mentor, 'mentor')
        ->create([
            'day_of_week' => (int) $target->format('w'),
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'timezone' => $session->timezone,
        ]);
}

/** Classify a real action response as success|forbidden|state|other. */
function classify($response, string $stateKey): array
{
    $status = $response->status();
    $kind = match (true) {
        in_array($status, [200, 201, 204], true) => 'success',
        in_array($status, [403, 404], true) => 'forbidden',
        $status === 422 && $response->json("errors.{$stateKey}") !== null => 'state',
        default => 'other',
    };

    return [$kind, $response->getContent()];
}


it('matches action availability with real endpoint outcomes', function (string $actorKind, string $moment): void {
    // Baseline: what the availability report claims.
    [$session, $actor] = coherenceScenario($actorKind, $moment);
    auth()->forgetGuards();
    $detail = $this->actingAs($actor)->getJson("/api/v1/sessions/{$session->id}");

    if ($actorKind === 'unrelated') {
        $detail->assertNotFound();

        return;
    }

    $actions = $detail->assertOk()->json('available_actions');
    expect($actions)->not->toBeNull();

    $attempts = [
        'reschedule' => fn (Session $fresh) => $this->putJson(
            "/api/v1/sessions/{$fresh->id}/reschedule",
            coherenceReschedulePayload($fresh),
        ),
        'cancel' => fn (Session $fresh) => $this->postJson(
            "/api/v1/sessions/{$fresh->id}/cancel",
            ['reason' => 'Coherence cancellation'],
        ),
        'complete' => fn (Session $fresh) => $this->putJson(
            "/api/v1/sessions/{$fresh->id}/status",
            ['status' => 'completed'],
        ),
    ];
    $stateKeys = ['reschedule' => 'scheduled_at', 'cancel' => 'status', 'complete' => 'session'];

    foreach ($attempts as $action => $request) {
        [$freshSession, $freshActor] = coherenceScenario($actorKind, $moment);
        coherenceRescheduleWindow($freshSession);
        auth()->forgetGuards();
        $this->actingAs($freshActor);
        [$kind, $body] = classify($request($freshSession), $stateKeys[$action]);

        $expected = $actions[$action];
        if ($expected['allowed']) {
            expect($kind, "{$actorKind}/{$moment}/{$action} allowed but got {$kind}: {$body}")->toBe('success');
        } elseif ($expected['reason'] === 'forbidden') {
            expect($kind, "{$actorKind}/{$moment}/{$action} forbidden but got {$kind}: {$body}")->toBe('forbidden');
        } else {
            expect($kind, "{$actorKind}/{$moment}/{$action} {$expected['reason']} but got {$kind}: {$body}")->toBe('state');
            if ($action === 'reschedule' && $kind === 'state') {
                $message = $expected['reason'] === 'insufficient_notice'
                    ? __('sessions.reschedule_notice')
                    : __('sessions.reschedule_not_eligible');
                expect(json_decode($body, true)['errors']['scheduled_at'][0] ?? null)->toBe($message);
            }
        }

        if ($action === 'cancel' && $kind === 'success') {
            expect((bool) $freshSession->fresh()->canceled_late)->toBe($actions['cancel']['late']);
        }
    }
})->with(collect([
    'mentor', 'client', 'responsible_family', 'member_minor',
    'responsible_company', 'admin_support', 'unrelated',
])->crossJoin([
    'future_far', 'future_late', 'in_progress', 'ended_window',
    'ended_closed', 'canceled', 'completed', 'not_completed',
])->all());

it('matches attendance availability with real confirmation outcomes', function (string $actorKind, string $moment): void {
    [$session, $actor] = coherenceScenario($actorKind, $moment);
    auth()->forgetGuards();
    $entries = $this->actingAs($actor)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->json('available_actions.attendance');

    // Entries are per-target and independent; reuse the reported session.
    foreach ($entries ?? [] as $entry) {
        auth()->forgetGuards();
        $payload = ['status' => 'attended'];
        if (! $entry['self']) {
            $payload['attendee_user_id'] = $entry['user_id'];
        }
        $response = $this->actingAs($actor)
            ->putJson("/api/v1/sessions/{$session->id}/attendance", $payload);

        if ($entry['allowed']) {
            $response->assertOk();
        } elseif ($entry['reason'] === 'already_confirmed_by_member') {
            // Keep semantics: the confirmation attempt succeeds but keeps the
            // member as the confirmer.
            $response->assertOk();
            $attendee = $session->fresh()->attendees->firstWhere('user_id', $entry['user_id']);
            expect((string) $attendee->confirmed_by)->toBe($entry['user_id']);
        } else {
            expect(
                in_array($response->status(), [403, 404], true)
                    || ($response->status() === 422 && $response->json('errors.attendance') !== null),
                "{$actorKind}/{$moment}/attendance {$entry['reason']} got {$response->status()}",
            )->toBeTrue();
        }
    }
})->with(collect([
    'client', 'mentor', 'member_minor', 'responsible_family', 'responsible_company',
])->crossJoin([
    'future_late', 'in_progress', 'ended_window', 'ended_closed', 'canceled', 'not_completed',
])->all());

it('lets collective responsibles confirm only the listed chargeable targets', function (string $actorKind, string $nonChargeableKey): void {
    [$session, $actor, $meta] = coherenceScenario($actorKind, 'ended_window');
    auth()->forgetGuards();
    $listed = collect(
        $this->actingAs($actor)
            ->getJson("/api/v1/sessions/{$session->id}")
            ->assertOk()
            ->json('available_actions.attendance'),
    )->pluck('user_id');

    $target = (string) $meta[$nonChargeableKey]->id;
    expect($listed)->not->toContain($target);

    auth()->forgetGuards();
    $response = $this->actingAs($actor)
        ->putJson("/api/v1/sessions/{$session->id}/attendance", [
            'status' => 'attended',
            'attendee_user_id' => $target,
        ]);

    expect(
        in_array($response->status(), [403, 404], true)
            || ($response->status() === 422 && $response->json('errors.attendance') !== null),
    )->toBeTrue();
})->with([
    'family adult member' => ['responsible_family', 'adult'],
    'company non-employee' => ['responsible_company', 'non_employee'],
]);

it('keeps a member confirmation when the responsible retries it (already_confirmed_by_member)', function (): void {
    [$session, $manager, $meta] = coherenceScenario('responsible_family', 'ended_window');
    $minor = $meta['minor'];
    SessionAttendee::query()
        ->where('session_id', $session->id)
        ->where('user_id', $minor->id)
        ->update(['confirmed_by' => $minor->id, 'attendance_status' => 'attended', 'confirmed_at' => now()]);

    auth()->forgetGuards();
    $entry = collect(
        $this->actingAs($manager)
            ->getJson("/api/v1/sessions/{$session->id}")
            ->assertOk()
            ->json('available_actions.attendance'),
    )->firstWhere('user_id', $minor->id);

    expect($entry['allowed'])->toBeFalse()
        ->and($entry['reason'])->toBe('already_confirmed_by_member');

    auth()->forgetGuards();
    $this->actingAs($manager)
        ->putJson("/api/v1/sessions/{$session->id}/attendance", [
            'status' => 'attended',
            'attendee_user_id' => $minor->id,
        ])
        ->assertOk();

    $attendee = $session->fresh()->attendees->firstWhere('user_id', $minor->id);
    expect((string) $attendee->confirmed_by)->toBe($minor->id);
});

it('returns the domain error when state changes between report and reschedule', function (): void {
    [$session, $client] = coherenceScenario('client', 'future_far');
    auth()->forgetGuards();

    // Mentor sees reschedule allowed.
    auth()->forgetGuards();
    $actions = $this->actingAs($session->relationship->mentor)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->json('available_actions');
    expect($actions['reschedule']['allowed'])->toBeTrue();

    // The client cancels in the meantime.
    auth()->forgetGuards();
    $this->actingAs($client)
        ->postJson("/api/v1/sessions/{$session->id}/cancel", ['reason' => 'Plans changed'])
        ->assertOk();

    // The mentor's stale allowance now surfaces the domain state error.
    auth()->forgetGuards();
    $this->actingAs($session->relationship->mentor)
        ->putJson("/api/v1/sessions/{$session->id}/reschedule", coherenceReschedulePayload($session))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['scheduled_at']);

    auth()->forgetGuards();
    $fresh = $this->actingAs($session->relationship->mentor)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->json('available_actions');
    expect($fresh['reschedule'])->toBe(['allowed' => false, 'reason' => 'terminal_status']);
});

it('returns the domain error when the session is canceled before attendance confirmation', function (): void {
    [$session, $client] = coherenceScenario('client', 'ended_window');
    auth()->forgetGuards();

    $entry = $this->actingAs($client)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->json('available_actions.attendance.0');
    expect($entry['allowed'])->toBeTrue();

    auth()->forgetGuards();
    $this->actingAs($client)
        ->postJson("/api/v1/sessions/{$session->id}/cancel", ['reason' => 'Plans changed'])
        ->assertOk();

    auth()->forgetGuards();
    $this->actingAs($client)
        ->putJson("/api/v1/sessions/{$session->id}/attendance", ['status' => 'attended'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance']);
});
