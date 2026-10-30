<?php

use App\Enums\AvailabilityMode;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\MentorshipRelationship;
use App\Models\MentorAvailabilityException;
use App\Models\Plan;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\SessionScheduleChange;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class)->group('scheduling', 'rescheduling');

function rescheduleUser(array $attributes = []): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->create(array_merge([
        'client_type' => 'personal',
        'is_active' => true,
    ], $attributes)));
}

function rescheduleRelationship(User $mentor, User $mentee, array $attributes = []): MentorshipRelationship
{
    return MentorshipRelationship::factory()->create(array_merge([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ], $attributes));
}

function rescheduleAtUtc(string $value): Carbon
{
    return Carbon::parse($value, 'UTC')->setTimezone((string) config('app.timezone'));
}

function rescheduleFixture(array $sessionOverrides = []): array
{
    $mentor = rescheduleUser(['role' => 'mentor', 'timezone' => 'Europe/Madrid']);
    $mentee = rescheduleUser(['role' => 'mentee']);
    $relationship = rescheduleRelationship($mentor, $mentee);
    $session = Session::factory()->create(array_merge([
        'relationship_id' => $relationship->id,
        'session_type' => 'individual',
        'title' => 'Revisión de objetivos',
        'scheduled_at' => rescheduleAtUtc('2026-10-20 14:00:00'),
        'timezone' => 'Europe/Madrid',
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ], $sessionOverrides));
    SessionAttendee::factory()->create(['session_id' => $session->id, 'user_id' => $mentor->id]);
    SessionAttendee::factory()->create(['session_id' => $session->id, 'user_id' => $mentee->id]);

    return [$session, $mentor, $mentee, $relationship];
}

function executeReschedule(Session $session, User $actor, array $overrides = [], ?Carbon $now = null): Session
{
    return app(\App\Actions\RescheduleSessionAction::class)->execute(
        $session->id,
        array_merge([
            'scheduled_at' => '2026-10-21T15:30:00+00:00',
            'duration_minutes' => 90,
            'timezone' => 'Asia/Tokyo',
            'reason' => 'Cambio de agenda',
        ], $overrides),
        $actor,
        $now ?? Carbon::parse('2026-10-13 10:00:00', 'UTC'),
    );
}

function rescheduleSnapshot(Session $session): array
{
    return (array) DB::table('sessions')->where('id', $session->id)->first();
}

it('reschedules the same session, recalculates its end, and appends complete timezone history', function () {
    [$session, $mentor, $mentee] = rescheduleFixture();
    $originalAttendees = $session->attendees()->orderBy('user_id')->get(['user_id'])->pluck('user_id')->all();
    $now = Carbon::parse('2026-10-13 10:00:00', 'UTC');

    $updated = executeReschedule($session, $mentee, now: $now);

    expect($updated->id)->toBe($session->id)
        ->and(Carbon::parse($updated->getRawOriginal('scheduled_at'), (string) config('app.timezone'))->utc()->toIso8601String())->toBe('2026-10-21T15:30:00+00:00')
        ->and($updated->timezone)->toBe('Asia/Tokyo')
        ->and($updated->duration_minutes)->toBe(90)
        ->and(Carbon::parse($updated->getRawOriginal('end_at'), (string) config('app.timezone'))->utc()->toIso8601String())->toBe('2026-10-21T17:00:00+00:00')
        ->and($updated->attendees()->orderBy('user_id')->get(['user_id'])->pluck('user_id')->all())->toBe($originalAttendees);

    $change = SessionScheduleChange::query()->sole();
    expect($change->session_id)->toBe($session->id)
        ->and($change->actor_id)->toBe($mentee->id)
        ->and(Carbon::parse($change->getRawOriginal('occurred_at'), (string) config('app.timezone'))->utc()->toIso8601String())->toBe('2026-10-13T10:00:00+00:00')
        ->and(Carbon::parse($change->getRawOriginal('scheduled_at_before'), (string) config('app.timezone'))->utc()->toIso8601String())->toBe('2026-10-20T14:00:00+00:00')
        ->and(Carbon::parse($change->getRawOriginal('scheduled_at_after'), (string) config('app.timezone'))->utc()->toIso8601String())->toBe('2026-10-21T15:30:00+00:00')
        ->and($change->timezone_before)->toBe('Europe/Madrid')
        ->and($change->timezone_after)->toBe('Asia/Tokyo')
        ->and($change->duration_minutes_before)->toBe(60)
        ->and($change->duration_minutes_after)->toBe(90)
        ->and($change->reason)->toBe('Cambio de agenda');
});

it('allows exactly two hours notice for the existing session', function () {
    [$session, $mentor] = rescheduleFixture(['scheduled_at' => rescheduleAtUtc('2026-10-13 12:00:00')]);

    expect(fn () => executeReschedule(
        $session,
        $mentor,
        now: Carbon::parse('2026-10-13 10:00:00', 'UTC'),
    ))->not->toThrow(ValidationException::class);
});

it('rolls back the session and history when the new start violates future or notice rules', function (string $newStart, string $errorField) {
    [$session, $mentor] = rescheduleFixture();
    $before = rescheduleSnapshot($session);

    expect(fn () => executeReschedule(
        $session,
        $mentor,
        ['scheduled_at' => $newStart],
    ))->toThrow(ValidationException::class);

    expect(rescheduleSnapshot($session))->toBe($before)
        ->and(SessionScheduleChange::query()->count())->toBe(0);
})->with([
    'new start in past' => ['2026-10-12T12:00:00+00:00', 'scheduled_at'],
]);

it('rejects an invalid timezone without changing the session or creating history', function () {
    [$session, $mentor] = rescheduleFixture();
    $before = rescheduleSnapshot($session);

    expect(fn () => executeReschedule($session, $mentor, ['timezone' => 'Mars/Olympus']))
        ->toThrow(ValidationException::class);

    expect(rescheduleSnapshot($session))->toBe($before)
        ->and(SessionScheduleChange::query()->count())->toBe(0);
});

it('rejects an out-of-range duration and an inactive relationship without mutation', function (string $relationshipStatus, array $input) {
    [$session, $mentor, , $relationship] = rescheduleFixture();
    $relationship->update(['status' => $relationshipStatus]);
    $before = rescheduleSnapshot($session);

    expect(fn () => executeReschedule($session, $mentor, $input))->toThrow(ValidationException::class);

    expect(rescheduleSnapshot($session))->toBe($before)
        ->and(SessionScheduleChange::query()->count())->toBe(0);
})->with([
    'duration below supported minimum' => ['active', ['duration_minutes' => 14]],
    'inactive relationship' => ['paused', []],
]);

it('rejects non scheduled, already started, and under-notice sessions without mutation', function (string $status, string $start, string $now) {
    [$session, $mentor] = rescheduleFixture([
        'status' => $status,
        'scheduled_at' => rescheduleAtUtc($start),
    ]);
    $before = rescheduleSnapshot($session);

    expect(fn () => executeReschedule($session, $mentor, now: Carbon::parse($now, 'UTC')))
        ->toThrow(ValidationException::class);

    expect(rescheduleSnapshot($session))->toBe($before)
        ->and(SessionScheduleChange::query()->count())->toBe(0);
})->with([
    'in progress' => ['in_progress', '2026-10-20 14:00:00', '2026-10-13 10:00:00'],
    'completed' => ['completed', '2026-10-20 14:00:00', '2026-10-13 10:00:00'],
    'canceled' => ['canceled', '2026-10-20 14:00:00', '2026-10-13 10:00:00'],
    'not completed' => ['not_completed', '2026-10-20 14:00:00', '2026-10-13 10:00:00'],
    'old start already passed' => ['scheduled', '2026-10-13 09:59:00', '2026-10-13 10:00:00'],
    'old start has under two hours' => ['scheduled', '2026-10-13 11:59:00', '2026-10-13 10:00:00'],
]);

it('revalidates overlap and preserves the original session if a participant conflicts', function () {
    [$session, $mentor, $mentee] = rescheduleFixture();
    $otherMentor = rescheduleUser(['role' => 'mentor']);
    $otherRelationship = rescheduleRelationship($otherMentor, $mentee);
    $conflict = Session::factory()->create([
        'relationship_id' => $otherRelationship->id,
        'scheduled_at' => rescheduleAtUtc('2026-10-21 15:00:00'),
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ]);
    SessionAttendee::factory()->create(['session_id' => $conflict->id, 'user_id' => $otherMentor->id]);
    SessionAttendee::factory()->create(['session_id' => $conflict->id, 'user_id' => $mentee->id]);
    $before = rescheduleSnapshot($session);

    expect(fn () => executeReschedule($session, $mentor))->toThrow(ValidationException::class);

    expect(rescheduleSnapshot($session))->toBe($before)
        ->and(SessionScheduleChange::query()->count())->toBe(0);
});

it('revalidates normalized duplicates against other sessions in the same relationship', function () {
    [$session, $mentor, $mentee, $relationship] = rescheduleFixture();
    $duplicate = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => rescheduleAtUtc('2026-10-21 15:30:00'),
        'duration_minutes' => 90,
        'title' => "  REVISIÓN   de objetivos ",
        'status' => 'scheduled',
    ]);
    SessionAttendee::factory()->create(['session_id' => $duplicate->id, 'user_id' => $mentor->id]);
    SessionAttendee::factory()->create(['session_id' => $duplicate->id, 'user_id' => $mentee->id]);
    $before = rescheduleSnapshot($session);

    expect(fn () => executeReschedule($session, $mentor))->toThrow(ValidationException::class);

    expect(rescheduleSnapshot($session))->toBe($before)
        ->and(SessionScheduleChange::query()->count())->toBe(0);
});

it('revalidates conflicts for active members of a collective relationship', function () {
    $mentor = rescheduleUser(['role' => 'mentor']);
    $member = rescheduleUser();
    $coordinator = rescheduleUser();
    $cohort = Cohort::create([
        'created_by' => $coordinator->id,
        'name' => 'Reschedule cohort',
        'status' => 'active',
    ]);
    CohortMember::create([
        'cohort_id' => $cohort->id,
        'user_id' => $member->id,
        'status' => 'active',
    ]);
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'grupal',
        'source_type' => 'cohort',
        'source_id' => $cohort->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'session_type' => 'group',
        'scheduled_at' => rescheduleAtUtc('2026-10-20 14:00:00'),
        'status' => 'scheduled',
    ]);
    SessionAttendee::factory()->create(['session_id' => $session->id, 'user_id' => $mentor->id]);
    SessionAttendee::factory()->create(['session_id' => $session->id, 'user_id' => $member->id]);

    $otherMentor = rescheduleUser(['role' => 'mentor']);
    $otherRelationship = rescheduleRelationship($otherMentor, $member);
    $conflict = Session::factory()->create([
        'relationship_id' => $otherRelationship->id,
        'scheduled_at' => rescheduleAtUtc('2026-10-21 15:00:00'),
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ]);
    SessionAttendee::factory()->create(['session_id' => $conflict->id, 'user_id' => $otherMentor->id]);
    SessionAttendee::factory()->create(['session_id' => $conflict->id, 'user_id' => $member->id]);
    $before = rescheduleSnapshot($session);

    expect(fn () => executeReschedule($session, $mentor))->toThrow(ValidationException::class);

    expect(rescheduleSnapshot($session))->toBe($before)
        ->and(SessionScheduleChange::query()->count())->toBe(0);
});

it('revalidates blocking availability exceptions even without availability windows', function () {
    [$session, $mentor] = rescheduleFixture();
    $exceptionDate = Carbon::parse('2026-10-21 15:30:00', 'UTC')->toDateString();
    MentorAvailabilityException::factory()->create([
        'user_id' => $mentor->id,
        'mode' => AvailabilityMode::Block,
        'start_date' => $exceptionDate,
        'end_date' => null,
        'start_time' => null,
        'end_time' => null,
        'timezone' => 'UTC',
    ]);
    $before = rescheduleSnapshot($session);

    expect(fn () => executeReschedule($session, $mentor))->toThrow(ValidationException::class);

    expect(rescheduleSnapshot($session))->toBe($before)
        ->and(SessionScheduleChange::query()->count())->toBe(0);
});

it('revalidates the new month plan quota but does not count the rescheduled session against itself', function () {
    [$session, $mentor, $mentee] = rescheduleFixture();
    $plan = Plan::create([
        'client_type' => 'personal',
        'name' => ['es' => 'Plan limitado'],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_sessions_per_month' => 1,
        'is_active' => true,
    ]);
    Subscription::create([
        'subscriber_type' => 'user',
        'subscriber_id' => $mentee->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addYear(),
    ]);

    expect(fn () => executeReschedule($session, $mentor, [
        'scheduled_at' => '2026-10-22T15:30:00+00:00',
    ]))->not->toThrow(ValidationException::class);

    $relationship = $session->relationship;
    Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => rescheduleAtUtc('2026-10-29 08:00:00'),
        'status' => 'scheduled',
    ]);
    $before = rescheduleSnapshot($session);

    expect(fn () => executeReschedule($session->fresh(), $mentor, [
        'scheduled_at' => '2026-10-24T15:30:00+00:00',
    ]))->toThrow(ValidationException::class);

    expect(rescheduleSnapshot($session))->toBe($before);
});

it('rejects unauthorized access according to the scheduling policy without modifying the session', function () {
    [$session, $mentor] = rescheduleFixture();
    $outsider = rescheduleUser();
    $before = rescheduleSnapshot($session);

    expect(fn () => executeReschedule($session, $outsider))->toThrow(AuthorizationException::class);

    expect(rescheduleSnapshot($session))->toBe($before)
        ->and(SessionScheduleChange::query()->count())->toBe(0);
});

it('allows support management only with the explicit scheduling permission', function () {
    [$session] = rescheduleFixture();
    $support = rescheduleUser(['role' => 'admin']);
    Role::findOrCreate('admin', 'web');
    $support->assignRole('admin');
    $support->givePermissionTo(Permission::findOrCreate('scheduling.manage.support', 'web'));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $updated = executeReschedule($session, $support);

    expect($updated->id)->toBe($session->id)
        ->and(SessionScheduleChange::query()->sole()->actor_id)->toBe($support->id);
});
