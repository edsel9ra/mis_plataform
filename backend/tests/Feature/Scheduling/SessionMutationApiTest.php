<?php

use App\Events\SessionCanceled;
use App\Events\SessionLifecycleTransitioned;
use App\Events\SessionRescheduled;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\SessionScheduleChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class)->group('scheduling', 'session-mutation-api');

function mutationApiFixture(array $sessionOverrides = []): array
{
    $mentor = User::factory()->mentor()->create();
    $mentee = User::factory()->create();
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
        'title' => 'API planning session',
        'scheduled_at' => Carbon::parse('2026-10-20 14:00:00', 'UTC'),
        'timezone' => 'Europe/Madrid',
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ], $sessionOverrides));
    SessionAttendee::factory()->create(['session_id' => $session->id, 'user_id' => $mentor->id]);
    SessionAttendee::factory()->create(['session_id' => $session->id, 'user_id' => $mentee->id]);

    return [$session, $mentor, $mentee, $relationship];
}

function mutationApiAdmin(): User
{
    $admin = User::factory()->create(['role' => 'admin']);
    Role::findOrCreate('admin', 'web');
    $admin->assignRole('admin');
    $admin->givePermissionTo(Permission::findOrCreate('scheduling.manage.support', 'web'));
    $admin->givePermissionTo(Permission::findOrCreate('scheduling.view.support', 'web'));
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $admin;
}

function mutationApiOriginal(Session $session): array
{
    return (array) DB::table('sessions')->where('id', $session->id)->first();
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-13 10:00:00', 'UTC'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('rejects schedule fields on the public generic update without mutating the session', function () {
    [$session, , $mentee] = mutationApiFixture();
    $before = mutationApiOriginal($session);

    $this->actingAs($mentee)
        ->putJson("/api/v1/sessions/{$session->id}", [
            'scheduled_at' => '2026-10-21T15:30:00+00:00',
            'duration_minutes' => 90,
            'timezone' => 'Asia/Tokyo',
            'end_at' => '2026-10-21T17:00:00+00:00',
        ])
        ->assertUnprocessable();

    expect(mutationApiOriginal($session))->toBe($before)
        ->and(SessionScheduleChange::query()->count())->toBe(0);
});

it('rejects schedule and status fields on the generic admin update without mutation', function () {
    [$session] = mutationApiFixture();
    $admin = mutationApiAdmin();
    $before = mutationApiOriginal($session);

    $this->actingAs($admin)
        ->putJson("/api/v1/admin/sessions/{$session->id}", [
            'scheduled_at' => '2026-10-21T15:30:00+00:00',
            'duration_minutes' => 90,
            'timezone' => 'Asia/Tokyo',
            'end_at' => '2026-10-21T17:00:00+00:00',
            'status' => 'canceled',
        ])
        ->assertUnprocessable();

    expect(mutationApiOriginal($session))->toBe($before)
        ->and(SessionScheduleChange::query()->count())->toBe(0);
});

it('reschedules through the public operation with full timezone history and the change event', function () {
    [$session, , $mentee] = mutationApiFixture();
    Event::fake([SessionRescheduled::class]);

    $this->actingAs($mentee)
        ->putJson("/api/v1/sessions/{$session->id}/reschedule", [
            'scheduled_at' => '2026-10-21T15:30:00+00:00',
            'duration_minutes' => 90,
            'timezone' => 'Asia/Tokyo',
            'reason' => 'Cambio de agenda',
        ])
        ->assertOk()
        ->assertJsonPath('scheduled_at', '2026-10-21T15:30:00.000000Z')
        ->assertJsonPath('duration_minutes', 90);

    $change = SessionScheduleChange::query()->sole();
    expect($change->session_id)->toBe($session->id)
        ->and($change->actor_id)->toBe($mentee->id)
        ->and($change->timezone_before)->toBe('Europe/Madrid')
        ->and($change->timezone_after)->toBe('Asia/Tokyo')
        ->and($change->reason)->toBe('Cambio de agenda');
    Event::assertDispatched(SessionRescheduled::class, fn (SessionRescheduled $event): bool => $event->sessionId === $session->id);
});

it('revalidates public rescheduling conflicts and leaves the original schedule and history intact', function () {
    [$session, , $mentee] = mutationApiFixture();
    $conflict = Session::factory()->create([
        'relationship_id' => $session->relationship_id,
        'title' => $session->title,
        'scheduled_at' => Carbon::parse('2026-10-21 15:30:00', 'UTC')
            ->setTimezone((string) config('app.timezone')),
        'duration_minutes' => 90,
        'status' => 'scheduled',
    ]);
    SessionAttendee::factory()->create(['session_id' => $conflict->id, 'user_id' => $session->relationship->mentor_id]);
    SessionAttendee::factory()->create(['session_id' => $conflict->id, 'user_id' => $mentee->id]);
    $before = mutationApiOriginal($session);

    $this->actingAs($mentee)
        ->putJson("/api/v1/sessions/{$session->id}/reschedule", [
            'scheduled_at' => '2026-10-21T15:30:00+00:00',
            'duration_minutes' => 90,
        ])
        ->assertUnprocessable();

    expect(mutationApiOriginal($session))->toBe($before)
        ->and(SessionScheduleChange::query()->count())->toBe(0);
});

it('routes admin rescheduling through the same validated action', function () {
    [$session] = mutationApiFixture();
    $admin = mutationApiAdmin();
    Event::fake([SessionRescheduled::class]);

    $this->actingAs($admin)
        ->putJson("/api/v1/admin/sessions/{$session->id}/reschedule", [
            'scheduled_at' => '2026-10-21T15:30:00+00:00',
            'timezone' => 'Asia/Tokyo',
        ])
        ->assertOk();

    expect(SessionScheduleChange::query()->sole()->actor_id)->toBe($admin->id);
    Event::assertDispatched(SessionRescheduled::class, fn (SessionRescheduled $event): bool => $event->sessionId === $session->id);
});

it('rejects cancellation through status endpoints without a reason and does not change status', function (bool $adminRoute) {
    [$session, , $mentee] = mutationApiFixture();
    $actor = $adminRoute ? mutationApiAdmin() : $mentee;
    $url = $adminRoute
        ? "/api/v1/admin/sessions/{$session->id}/status"
        : "/api/v1/sessions/{$session->id}/status";
    $before = mutationApiOriginal($session);

    $this->actingAs($actor)
        ->putJson($url, ['status' => 'canceled'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    expect(mutationApiOriginal($session))->toBe($before);
})->with([false, true]);

it('delegates an authorized admin status cancellation to the traced action', function () {
    [$session] = mutationApiFixture([
        'meet_link' => 'https://meet.example/admin-historical-link',
        'meet_event_id' => 'admin-event-id',
    ]);
    $admin = mutationApiAdmin();
    Event::fake([SessionCanceled::class]);

    $this->actingAs($admin)
        ->putJson("/api/v1/admin/sessions/{$session->id}/status", [
            'status' => 'canceled',
            'reason' => 'Soporte autorizado',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'canceled')
        ->assertJsonPath('meet_link', null);

    expect($session->fresh()->status)->toBe('canceled')
        ->and($session->fresh()->canceled_by)->toBe($admin->id)
        ->and($session->fresh()->cancellation_reason)->toBe('Soporte autorizado')
        ->and($session->fresh()->meet_link)->toBe('https://meet.example/admin-historical-link');
    Event::assertDispatched(SessionCanceled::class);
});

it('delegates public status cancellation with a reason to the traced cancellation action', function () {
    [$session, , $mentee] = mutationApiFixture([
        'meet_link' => 'https://meet.example/historic-link',
        'meet_event_id' => 'external-event-id',
    ]);
    Event::fake([SessionCanceled::class]);

    $this->actingAs($mentee)
        ->putJson("/api/v1/sessions/{$session->id}/status", [
            'status' => 'canceled',
            'reason' => 'No podré asistir',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'canceled')
        ->assertJsonPath('meet_link', null);

    expect($session->fresh()->status)->toBe('canceled')
        ->and($session->fresh()->canceled_by)->toBe($mentee->id)
        ->and($session->fresh()->cancellation_reason)->toBe('No podré asistir')
        ->and($session->fresh()->meet_link)->toBe('https://meet.example/historic-link');
    Event::assertDispatched(SessionCanceled::class);

    $this->putJson("/api/v1/sessions/{$session->id}/status", ['status' => 'scheduled'])
        ->assertForbidden();
    $this->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->assertJsonPath('status', 'canceled')
        ->assertJsonPath('meet_link', null);
});

it('rejects status reopening of canceled sessions on public and admin routes', function (bool $adminRoute) {
    [$session, $mentor] = mutationApiFixture([
        'status' => 'canceled',
        'cancellation_reason' => 'Histórico',
        'meet_link' => 'https://meet.example/historic-link',
    ]);
    $actor = $adminRoute ? mutationApiAdmin() : $mentor;
    $url = $adminRoute
        ? "/api/v1/admin/sessions/{$session->id}/status"
        : "/api/v1/sessions/{$session->id}/status";

    $this->actingAs($actor)
        ->putJson($url, ['status' => 'scheduled'])
        ->assertUnprocessable();

    expect($session->fresh()->status)->toBe('canceled')
        ->and($session->fresh()->meet_link)->toBe('https://meet.example/historic-link');

    $showPath = $adminRoute
        ? "/api/v1/admin/sessions/{$session->id}"
        : "/api/v1/sessions/{$session->id}";
    $this->getJson($showPath)
        ->assertOk()
        ->assertJsonPath('status', 'canceled')
        ->assertJsonPath('meet_link', null);

    if ($adminRoute) {
        $this->getJson('/api/v1/admin/sessions?status=canceled')
            ->assertOk()
            ->assertJsonPath('data.0.meet_link', null);
    }
})->with([false, true]);

it('rejects direct completion and other lifecycle transitions through both status endpoints', function (bool $adminRoute) {
    [$session, $mentor] = mutationApiFixture();
    $actor = $adminRoute ? mutationApiAdmin() : $mentor;
    $url = $adminRoute
        ? "/api/v1/admin/sessions/{$session->id}/status"
        : "/api/v1/sessions/{$session->id}/status";

    $this->actingAs($actor)
        ->putJson($url, ['status' => 'completed'])
        ->assertUnprocessable();

    expect($session->fresh()->status)->toBe('scheduled');
})->with([false, true]);

it('rejects non-scheduled initial states on admin session creation without lifecycle side effects', function (string $status) {
    $mentor = User::factory()->mentor()->create();
    $mentee = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);
    $admin = mutationApiAdmin();
    Event::fake([SessionCanceled::class, SessionLifecycleTransitioned::class, SessionRescheduled::class]);

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/sessions', [
            'relationship_id' => $relationship->id,
            'session_type' => 'individual',
            'title' => 'Admin-created session',
            'scheduled_at' => '2026-10-20T14:00:00+00:00',
            'duration_minutes' => 60,
            'status' => $status,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect(Session::query()->count())->toBe(0)
        ->and(SessionAttendee::query()->count())->toBe(0);
    Event::assertNotDispatched(SessionCanceled::class);
    Event::assertNotDispatched(SessionLifecycleTransitioned::class);
})->with(['in_progress', 'completed', 'canceled', 'not_completed']);

it('creates admin sessions only in the scheduled state by default or explicitly', function (?string $status) {
    $mentor = User::factory()->mentor()->create();
    $mentee = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);
    $admin = mutationApiAdmin();
    Event::fake([SessionCanceled::class, SessionLifecycleTransitioned::class, SessionRescheduled::class]);
    $payload = [
        'relationship_id' => $relationship->id,
        'session_type' => 'individual',
        'title' => 'Admin-created session',
        'scheduled_at' => '2026-10-20T14:00:00+00:00',
        'duration_minutes' => 60,
    ];

    if ($status !== null) {
        $payload['status'] = $status;
    }

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/sessions', $payload)
        ->assertCreated()
        ->assertJsonPath('status', 'scheduled');

    expect(Session::query()->count())->toBe(1)
        ->and(Session::query()->sole()->status)->toBe('scheduled')
        ->and(SessionAttendee::query()->count())->toBe(2);
})->with([null, 'scheduled']);

it('delegates a valid public completion to the lifecycle action', function () {
    [$session, $mentor] = mutationApiFixture([
        'scheduled_at' => Carbon::parse('2026-10-13 08:00:00', 'UTC')
            ->setTimezone((string) config('app.timezone')),
    ]);

    $this->actingAs($mentor)
        ->putJson("/api/v1/sessions/{$session->id}/status", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    expect($session->fresh()->status)->toBe('completed')
        ->and($session->fresh()->completed_by)->toBe($mentor->id)
        ->and($session->attendees()->whereHas('attendanceEvents')->count())->toBeGreaterThan(0);
});
