<?php

use App\Actions\CancelSessionAction;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class)->group('scheduling', 'cancellation');

function cancelFixture(array $overrides = []): array
{
    $mentor = User::factory()->mentor()->create();
    $mentee = User::factory()->create();
    $outsider = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);
    $session = Session::factory()->create(array_merge([
        'relationship_id' => $relationship->id,
        'session_type' => 'individual',
        'scheduled_at' => cancelAtUtc('2026-10-13 12:00:00'),
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ], $overrides));
    $mentorAttendee = SessionAttendee::factory()->create([
        'session_id' => $session->id,
        'user_id' => $mentor->id,
    ]);
    $menteeAttendee = SessionAttendee::factory()->create([
        'session_id' => $session->id,
        'user_id' => $mentee->id,
    ]);

    return [$session, $mentor, $mentee, $outsider, $mentorAttendee, $menteeAttendee];
}

function cancelAtUtc(string $value): Carbon
{
    return Carbon::parse($value, 'UTC')->setTimezone((string) config('app.timezone'));
}

function runCancel(Session $session, User $actor, ?string $reason = 'Cambio solicitado', ?Carbon $now = null): Session
{
    return app(CancelSessionAction::class)->execute(
        $session->id,
        $reason,
        $actor,
        $now ?? Carbon::parse('2026-10-13 10:00:00', 'UTC'),
    );
}

it('cancels a scheduled session at exactly two hours and preserves participants with attribution', function () {
    [$session, , $mentee, , $mentorAttendee, $menteeAttendee] = cancelFixture();

    $canceled = runCancel($session, $mentee);

    expect($canceled->status)->toBe('canceled')
        ->and($canceled->canceled_by)->toBe($mentee->id)
        ->and($canceled->cancellation_reason)->toBe('Cambio solicitado')
        ->and($canceled->canceled_late)->toBeFalse()
        ->and($canceled->cancellation_lead_minutes)->toBe(120)
        ->and($canceled->canceled_at->utc()->toIso8601String())->toBe('2026-10-13T10:00:00+00:00')
        ->and(Session::query()->whereKey($session->id)->exists())->toBeTrue()
        ->and(SessionAttendee::query()->whereKey($mentorAttendee->id)->exists())->toBeTrue()
        ->and(SessionAttendee::query()->whereKey($menteeAttendee->id)->exists())->toBeTrue();
});

it('accepts and marks a scheduled cancellation with under two hours actual lead', function () {
    [$session, $mentor] = cancelFixture();

    $canceled = runCancel($session, $mentor, 'Urgencia', Carbon::parse('2026-10-13 10:00:01', 'UTC'));

    expect($canceled->status)->toBe('canceled')
        ->and($canceled->canceled_late)->toBeTrue()
        ->and($canceled->cancellation_lead_minutes)->toBe(119);
});

it('allows in-progress cancellation and records negative actual lead after start', function () {
    [$session, $mentor] = cancelFixture([
        'status' => 'in_progress',
        'scheduled_at' => cancelAtUtc('2026-10-13 09:30:00'),
    ]);

    $canceled = runCancel($session, $mentor, 'Interrumpida', Carbon::parse('2026-10-13 10:00:00', 'UTC'));

    expect($canceled->status)->toBe('canceled')
        ->and($canceled->canceled_late)->toBeTrue()
        ->and($canceled->cancellation_lead_minutes)->toBe(-30);
});

it('rejects missing or blank cancellation reason without changing the session', function (?string $reason) {
    [$session, $mentor] = cancelFixture();
    $before = (array) DB::table('sessions')->where('id', $session->id)->first();

    expect(fn () => runCancel($session, $mentor, $reason))->toThrow(ValidationException::class);

    expect((array) DB::table('sessions')->where('id', $session->id)->first())->toBe($before);
})->with([
    'missing' => [null],
    'blank' => ['   '],
]);

it('rejects terminal states without changing cancellation fields', function (string $status) {
    [$session, $mentor] = cancelFixture(['status' => $status]);
    $before = (array) DB::table('sessions')->where('id', $session->id)->first();

    expect(fn () => runCancel($session, $mentor))->toThrow(ValidationException::class);

    expect((array) DB::table('sessions')->where('id', $session->id)->first())->toBe($before);
})->with(['completed', 'canceled', 'not_completed']);

it('enforces the session policy for an outsider', function () {
    [$session, , , $outsider] = cancelFixture();

    expect(fn () => runCancel($session, $outsider))->toThrow(AuthorizationException::class);
    expect($session->fresh()->status)->toBe('scheduled');
});

it('writes a support audit entry with actor and before-after chronology', function () {
    [$session] = cancelFixture();
    $support = User::factory()->create(['role' => 'admin']);
    Role::findOrCreate('admin', 'web');
    $support->assignRole('admin');
    $support->givePermissionTo(Permission::findOrCreate('scheduling.manage.support', 'web'));
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $now = Carbon::parse('2026-10-13 10:00:00', 'UTC');

    runCancel($session, $support, 'Soporte', $now);

    $activity = Activity::query()
        ->where('log_name', 'scheduling')
        ->where('subject_type', Session::class)
        ->where('subject_id', $session->id)
        ->where('event', 'support.session.canceled')
        ->sole();

    expect($activity->causer_id)->toBe($support->id)
        ->and($activity->properties['before']['status'])->toBe('scheduled')
        ->and($activity->properties['after']['status'])->toBe('canceled')
        ->and($activity->properties['changed_fields'])->toContain('cancellation_reason', 'canceled_by', 'canceled_at');
});

it('requires reason on the cancellation API and persists a valid request', function () {
    [$session, , $mentee] = cancelFixture();
    Carbon::setTestNow(Carbon::parse('2026-10-13 10:00:00', 'UTC'));
    $this->actingAs($mentee)->postJson("/api/v1/sessions/{$session->id}/cancel", [])->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    $response = $this->postJson("/api/v1/sessions/{$session->id}/cancel", ['reason' => 'No podré asistir']);

    $response->assertOk()->assertJsonPath('status', 'canceled');
    expect($session->fresh()->cancellation_reason)->toBe('No podré asistir');
});

it('hides a retained Meet link from canceled session API responses and manual Meet lookup', function () {
    [$session, , $mentee] = cancelFixture([
        'meet_link' => 'https://meet.example/historical-secret',
        'meet_event_id' => 'google-event-123',
    ]);
    Carbon::setTestNow(Carbon::parse('2026-10-13 10:00:00', 'UTC'));

    $this->actingAs($mentee)
        ->postJson("/api/v1/sessions/{$session->id}/cancel", ['reason' => 'No podré asistir'])
        ->assertOk()
        ->assertJsonPath('status', 'canceled')
        ->assertJsonPath('meet_link', null);

    expect($session->fresh()->meet_link)->toBe('https://meet.example/historical-secret')
        ->and($session->fresh()->meet_event_id)->toBe('google-event-123');

    $this->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->assertJsonPath('meet_link', null);

    $this->getJson('/api/v1/sessions')
        ->assertOk()
        ->assertJsonPath('data.0.meet_link', null);

    $this->postJson("/api/v1/sessions/{$session->id}/meet-link")
        ->assertOk()
        ->assertJsonPath('meet_link', null);
});
