<?php

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses()->group('scheduling', 'authorization');

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('super_admin', 'web');
});

function schedulingAuthorizationActor(array $attributes = []): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->create(array_merge([
        'client_type' => 'personal',
        'is_active' => true,
    ], $attributes)));
}

function schedulingAuthorizationSession(array $relationshipAttributes = []): Session
{
    $relationship = MentorshipRelationship::factory()->create($relationshipAttributes);

    return Session::factory()->create(['relationship_id' => $relationship->id]);
}

it('allows the mentee of a relationship to view and create its sessions', function () {
    $mentee = schedulingAuthorizationActor();
    $mentor = schedulingAuthorizationActor(['role' => 'mentor']);
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
    ]);
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => now()->addDays(10),
    ]);

    $this->actingAs($mentee)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk();

    $this->actingAs($mentee)
        ->postJson('/api/v1/sessions', [
            'relationship_id' => $relationship->id,
            'session_type' => 'individual',
            'title' => 'Session requested by mentee',
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'duration_minutes' => 60,
        ])
        ->assertCreated();
});

it('allows a collective manager to view and create sessions for its collective', function () {
    $manager = schedulingAuthorizationActor();
    $mentor = schedulingAuthorizationActor(['role' => 'mentor']);
    $cohort = Cohort::create([
        'created_by' => $manager->id,
        'name' => 'Managed cohort',
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
    ]);

    $this->actingAs($manager)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk();

    $this->actingAs($manager)
        ->getJson('/api/v1/sessions')
        ->assertOk()
        ->assertJsonFragment(['id' => $session->id]);

    $otherManager = schedulingAuthorizationActor();
    $otherCohort = Cohort::create([
        'created_by' => $otherManager->id,
        'name' => 'Other cohort',
        'status' => 'active',
    ]);
    $otherRelationship = MentorshipRelationship::factory()->create([
        'type' => 'grupal',
        'source_type' => 'cohort',
        'source_id' => $otherCohort->id,
        'mentor_id' => $mentor->id,
    ]);
    $otherSession = Session::factory()->create([
        'relationship_id' => $otherRelationship->id,
        'session_type' => 'group',
    ]);

    $this->actingAs($manager)
        ->getJson("/api/v1/sessions/{$otherSession->id}")
        ->assertNotFound();

    $this->actingAs($manager)
        ->postJson('/api/v1/sessions', [
            'relationship_id' => $relationship->id,
            'session_type' => 'group',
            'title' => 'Cohort session',
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'duration_minutes' => 60,
        ])
        ->assertCreated();
});

it('allows a collective participant to view but not manage the session', function () {
    $manager = schedulingAuthorizationActor();
    $member = schedulingAuthorizationActor();
    $mentor = schedulingAuthorizationActor(['role' => 'mentor']);
    $cohort = Cohort::create([
        'created_by' => $manager->id,
        'name' => 'Participant cohort',
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
    ]);

    $this->actingAs($member)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk();

    $this->actingAs($member)
        ->putJson("/api/v1/sessions/{$session->id}", ['title' => 'Unauthorized change'])
        ->assertForbidden();

    $this->actingAs($member)
        ->putJson("/api/v1/sessions/{$session->id}/status", ['mentee_notes' => 'Unauthorized notes'])
        ->assertForbidden();

    $this->actingAs($member)
        ->postJson('/api/v1/sessions', [
            'relationship_id' => $relationship->id,
            'session_type' => 'group',
            'title' => 'Unauthorized cohort session',
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'duration_minutes' => 60,
        ])
        ->assertForbidden();
});

it('returns not found when an unrelated actor manipulates a session identifier', function () {
    $session = schedulingAuthorizationSession();
    $outsider = schedulingAuthorizationActor();

    $this->actingAs($outsider)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertNotFound();

    $this->actingAs($outsider)
        ->putJson("/api/v1/sessions/{$session->id}", ['title' => 'IDOR'])
        ->assertNotFound();
});

it('does not distinguish a missing relationship from a relationship the actor cannot access', function () {
    $mentor = schedulingAuthorizationActor(['role' => 'mentor']);
    $owner = schedulingAuthorizationActor();
    $outsider = schedulingAuthorizationActor();
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $owner->id,
        'mentor_id' => $mentor->id,
    ]);
    $payload = [
        'relationship_id' => $relationship->id,
        'session_type' => 'individual',
        'title' => 'Unauthorized relationship',
        'scheduled_at' => now()->addDays(2)->toIso8601String(),
        'duration_minutes' => 60,
    ];

    $this->actingAs($outsider)
        ->postJson('/api/v1/sessions', $payload)
        ->assertNotFound();

    $payload['relationship_id'] = (string) Str::uuid();

    $this->actingAs($outsider)
        ->postJson('/api/v1/sessions', $payload)
        ->assertNotFound();
});

it('does not grant admins session access from their role alone', function () {
    $admin = schedulingAuthorizationActor(['role' => 'admin']);
    $admin->assignRole('admin');
    $session = schedulingAuthorizationSession();

    $this->actingAs($admin)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertNotFound();

    $this->actingAs($admin)
        ->getJson('/api/v1/sessions')
        ->assertForbidden();

    $this->actingAs($admin)
        ->getJson("/api/v1/admin/sessions/{$session->id}")
        ->assertForbidden();
});

it('separates support read and manage permissions', function () {
    $viewer = schedulingAuthorizationActor(['role' => 'admin']);
    $viewer->assignRole('admin');
    $viewer->givePermissionTo(Permission::findOrCreate('scheduling.view.support', 'web'));
    $manager = schedulingAuthorizationActor(['role' => 'admin']);
    $manager->assignRole('admin');
    $manager->givePermissionTo(Permission::findOrCreate('scheduling.manage.support', 'web'));
    $session = schedulingAuthorizationSession();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($viewer)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk();

    $this->actingAs($viewer)
        ->getJson('/api/v1/sessions')
        ->assertOk()
        ->assertJsonFragment(['id' => $session->id]);

    $this->actingAs($viewer)
        ->putJson("/api/v1/sessions/{$session->id}", ['title' => 'No manage permission'])
        ->assertForbidden();

    $this->actingAs($manager)
        ->putJson("/api/v1/sessions/{$session->id}", ['title' => 'Support update'])
        ->assertNoContent();

    $this->actingAs($manager)
        ->putJson("/api/v1/admin/sessions/{$session->id}", ['title' => 'Support update'])
        ->assertNoContent();

    $this->actingAs($manager)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertNotFound();

    $this->actingAs($viewer)
        ->getJson("/api/v1/admin/sessions/{$session->id}")
        ->assertOk();

    $this->actingAs($viewer)
        ->putJson("/api/v1/admin/sessions/{$session->id}", ['title' => 'Support update'])
        ->assertForbidden();
});

it('restricts physical deletion to explicit support management', function () {
    $mentor = schedulingAuthorizationActor(['role' => 'mentor']);
    $mentee = schedulingAuthorizationActor();
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
    ]);
    $session = Session::factory()->create(['relationship_id' => $relationship->id]);
    $supportViewer = schedulingAuthorizationActor(['role' => 'admin']);
    $supportViewer->assignRole('admin');
    $supportViewer->givePermissionTo(Permission::findOrCreate('scheduling.view.support', 'web'));
    $supportManager = schedulingAuthorizationActor(['role' => 'admin']);
    $supportManager->assignRole('admin');
    $supportManager->givePermissionTo(Permission::findOrCreate('scheduling.manage.support', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($mentor)
        ->deleteJson("/api/v1/sessions/{$session->id}")
        ->assertForbidden();

    $this->actingAs($supportViewer)
        ->deleteJson("/api/v1/sessions/{$session->id}")
        ->assertForbidden();

    $this->actingAs($supportManager)
        ->deleteJson("/api/v1/sessions/{$session->id}")
        ->assertOk();

    $this->assertSoftDeleted($session);
});

it('seeds explicit support permissions for platform administrators', function () {
    app(RolePermissionSeeder::class)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::whereIn('name', [
        'scheduling.view.support',
        'scheduling.manage.support',
    ])->count())->toBe(2);

    $adminRole = \Spatie\Permission\Models\Role::findByName('admin', 'web');
    $superAdminRole = \Spatie\Permission\Models\Role::findByName('super_admin', 'web');

    expect($adminRole->hasPermissionTo('scheduling.view.support'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('scheduling.manage.support'))->toBeTrue()
        ->and($superAdminRole->hasPermissionTo('scheduling.view.support'))->toBeTrue()
        ->and($superAdminRole->hasPermissionTo('scheduling.manage.support'))->toBeTrue();
});
