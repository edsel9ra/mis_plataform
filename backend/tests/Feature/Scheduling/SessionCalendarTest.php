<?php

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class)->group('scheduling', 'calendar');

function calendarActor(array $attributes = []): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->create(array_merge([
        'client_type' => 'personal',
        'is_active' => true,
    ], $attributes)));
}

function calendarSessionFor(User $participant, string $scheduledAt, array $attributes = []): Session
{
    $mentor = calendarActor(['role' => 'mentor']);
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $participant->id,
        'mentor_id' => $mentor->id,
    ]);

    return Session::factory()->create(array_merge([
        'relationship_id' => $relationship->id,
        'scheduled_at' => dbInstant($scheduledAt),
    ], $attributes));
}

it('filters participant sessions by a semi-open range without exposing other sessions', function () {
    $participant = calendarActor();
    $from = '2026-05-01T06:00:00-04:00';
    $to = '2026-05-02T06:00:00-04:00';
    $atFrom = calendarSessionFor($participant, '2026-05-01T10:00:00Z');
    $beforeTo = calendarSessionFor($participant, '2026-05-02T09:59:59Z');
    calendarSessionFor($participant, '2026-05-02T10:00:00Z');
    calendarSessionFor(calendarActor(), '2026-05-01T12:00:00Z', ['title' => 'Private other session']);

    $response = $this->actingAs($participant)->getJson('/api/v1/sessions?from='.$from.'&to='.$to);

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['id' => $atFrom->id])
        ->assertJsonFragment(['id' => $beforeTo->id])
        ->assertJsonMissing(['title' => 'Private other session']);
});

it('allows collective participants and explicitly requested authorized managers', function () {
    $manager = calendarActor();
    $member = calendarActor();
    $mentor = calendarActor(['role' => 'mentor']);
    $cohort = Cohort::create([
        'created_by' => $manager->id,
        'name' => 'Calendar cohort',
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
    ]);
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'session_type' => 'group',
        'scheduled_at' => dbInstant('2026-05-01T12:00:00Z'),
    ]);
    $range = '?from=2026-05-01T00:00:00Z&to=2026-05-02T00:00:00Z';

    $this->actingAs($member)
        ->getJson('/api/v1/sessions'.$range)
        ->assertOk()
        ->assertJsonFragment(['id' => $session->id]);

    $this->actingAs($manager)
        ->getJson('/api/v1/sessions'.$range)
        ->assertOk()
        ->assertJsonMissing(['id' => $session->id]);

    $this->actingAs($manager)
        ->getJson('/api/v1/sessions'.$range.'&include_collectives=1')
        ->assertOk()
        ->assertJsonFragment(['id' => $session->id]);

    $this->actingAs(calendarActor())
        ->getJson('/api/v1/sessions'.$range.'&include_collectives=1')
        ->assertOk()
        ->assertJsonMissing(['id' => $session->id]);
});

it('does not grant list access to support roles without explicit read permission', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::findOrCreate('admin', 'web');
    $admin = calendarActor(['role' => 'admin']);
    $admin->assignRole('admin');
    calendarSessionFor(calendarActor(), '2026-05-01T12:00:00Z');

    $this->actingAs($admin)
        ->getJson('/api/v1/sessions?from=2026-05-01T00:00:00Z&to=2026-05-02T00:00:00Z')
        ->assertForbidden();
});

it('allows explicit support readers to query MIS sessions within the requested range', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::findOrCreate('admin', 'web');
    $admin = calendarActor(['role' => 'admin']);
    $admin->assignRole('admin');
    $admin->givePermissionTo(Permission::findOrCreate('scheduling.view.support', 'web'));
    $session = calendarSessionFor(calendarActor(), '2026-05-01T12:00:00Z');
    calendarSessionFor(calendarActor(), '2026-05-03T12:00:00Z');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($admin)
        ->getJson('/api/v1/sessions?from=2026-05-01T00:00:00Z&to=2026-05-02T00:00:00Z')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['id' => $session->id]);
});

it('retains canceled sessions in the MIS range while masking their stored Meet link', function () {
    $participant = calendarActor();
    $session = calendarSessionFor($participant, '2026-05-01T12:00:00Z', [
        'status' => 'canceled',
        'meet_link' => 'https://meet.example.test/private',
    ]);

    $this->actingAs($participant)
        ->getJson('/api/v1/sessions?from=2026-05-01T00:00:00Z&to=2026-05-02T00:00:00Z')
        ->assertOk()
        ->assertJsonPath('data.0.id', $session->id)
        ->assertJsonPath('data.0.status', 'canceled')
        ->assertJsonPath('data.0.meet_link', null)
        ->assertJsonMissing(['meet_link' => 'https://meet.example.test/private']);

    expect($session->fresh()->meet_link)->toBe('https://meet.example.test/private');
});

it('validates paired ISO timestamps and requires the range end to follow its start', function () {
    $participant = calendarActor();

    $this->actingAs($participant)
        ->getJson('/api/v1/sessions?from=2026-05-01T00:00:00Z')
        ->assertUnprocessable();

    $this->actingAs($participant)
        ->getJson('/api/v1/sessions?from=2026-05-02T00:00:00Z&to=2026-05-01T00:00:00Z')
        ->assertUnprocessable();

    $this->actingAs($participant)
        ->getJson('/api/v1/sessions?from=2026-05-01&to=2026-05-02')
        ->assertUnprocessable();

    $this->actingAs($participant)
        ->getJson('/api/v1/sessions?from=2026-02-30T00:00:00Z&to=2026-03-01T00:00:00Z')
        ->assertUnprocessable();
});
