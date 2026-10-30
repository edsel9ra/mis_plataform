<?php

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses()->group('scheduling', 'audit');

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('super_admin', 'web');
});

function schedulingAuditUser(array $attributes = []): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->create(array_merge([
        'client_type' => 'personal',
        'is_active' => true,
    ], $attributes)));
}

function schedulingAuditActivity(Session $session): Activity
{
    return Activity::query()
        ->where('log_name', 'scheduling')
        ->where('subject_type', Session::class)
        ->where('subject_id', $session->id)
        ->latest('id')
        ->firstOrFail();
}

it('audits support changes with actor, moment, safe fields, and no free-text personal data', function () {
    $admin = schedulingAuditUser(['role' => 'admin']);
    $admin->assignRole('admin');
    $admin->givePermissionTo(Permission::findOrCreate('scheduling.manage.support', 'web'));

    $relationship = MentorshipRelationship::factory()->create();
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => now()->addDays(2),
        'description' => 'Private original text',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($admin)
        ->putJson("/api/v1/sessions/{$session->id}", [
            'description' => 'Private updated text',
        ])
        ->assertNoContent();

    $activity = schedulingAuditActivity($session);
    $properties = $activity->properties->toArray();

    expect($activity->causer_id)->toBe($admin->id)
        ->and($activity->event)->toBe('support.session.updated')
        ->and($activity->created_at)->not->toBeNull()
        ->and($properties['session_id'])->toBe($session->id)
        ->and($properties['changed_fields'])->toBe(['description'])
        ->and(json_encode($properties))->not->toContain('Private updated text')
        ->and(json_encode($properties))->not->toContain('Private original text');
});

it('audits a collective manager changing a session affecting other members', function () {
    $manager = schedulingAuditUser();
    $mentor = schedulingAuditUser(['role' => 'mentor']);
    $cohort = Cohort::create([
        'created_by' => $manager->id,
        'name' => 'Audit cohort',
        'status' => 'active',
    ]);
    CohortMember::create([
        'cohort_id' => $cohort->id,
        'user_id' => schedulingAuditUser()->id,
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
        ->putJson("/api/v1/sessions/{$session->id}", ['title' => 'Updated group session'])
        ->assertOk();

    $activity = schedulingAuditActivity($session);

    expect($activity->causer_id)->toBe($manager->id)
        ->and($activity->event)->toBe('collective.session.updated')
        ->and($activity->properties['changed_fields'])->toBe(['title']);
});

it('audits support operations through the dedicated admin session routes', function () {
    $admin = schedulingAuditUser(['role' => 'admin']);
    $admin->assignRole('admin');
    $admin->givePermissionTo(Permission::findOrCreate('scheduling.manage.support', 'web'));
    $session = Session::factory()->create();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($admin)
        ->putJson("/api/v1/admin/sessions/{$session->id}/status", [
            'status' => 'canceled',
            'reason' => 'Cancelación de soporte',
        ])
        ->assertNoContent();

    $activity = schedulingAuditActivity($session);

    expect($activity->causer_id)->toBe($admin->id)
        ->and($activity->event)->toBe('support.session.canceled')
        ->and($activity->properties['before']['status'])->toBe('scheduled')
        ->and($activity->properties['after']['status'])->toBe('canceled');

    $this->actingAs($admin)
        ->deleteJson("/api/v1/admin/sessions/{$session->id}")
        ->assertOk();

    expect(schedulingAuditActivity($session)->event)->toBe('support.session.deleted');
});

it('publishes the approved rescheduling audit fields without implementing the history', function () {
    expect(class_exists(App\Contracts\Scheduling\SessionScheduleChangeContract::class))->toBeTrue();

    $requiredFields = App\Contracts\Scheduling\SessionScheduleChangeContract::FIELDS;

    expect($requiredFields)->toContain(
        'timezone_before',
        'timezone_after',
        'actor_id',
        'occurred_at',
    );
    expect(App\Contracts\Scheduling\SessionScheduleChangeContract::OPTIONAL_FIELDS)->toContain('reason');
});
