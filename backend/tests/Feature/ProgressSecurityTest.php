<?php

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Objective;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses()->group('progress', 'security');

function progressSecurityUser(array $attributes = []): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->create(array_merge([
        'client_type' => 'personal',
        'is_active' => true,
    ], $attributes)));
}

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->supportPermission = Permission::query()->firstOrCreate(
        ['name' => 'progress.view.support', 'guard_name' => 'web'],
    );
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('mentor', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('does not reveal another user panel through a manipulated subject_id', function () {
    $viewer = progressSecurityUser();
    $other = progressSecurityUser();
    Objective::factory()->individual($other)->achieved()->create();

    $this->actingAs($viewer)
        ->getJson('/api/v1/progress/panel?subject_id='.$other->id)
        ->assertNotFound();
});

it('logs a structured access denial without panel content', function () {
    $viewer = progressSecurityUser();
    $other = progressSecurityUser();

    Log::spy();

    $this->actingAs($viewer)
        ->getJson('/api/v1/progress/panel?subject_id='.$other->id)
        ->assertNotFound();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message, $context = []) => $message === 'progress.access_denied'
            && ($context['user_id'] ?? null) === $viewer->id
            && ($context['subject_id'] ?? null) === $other->id
            && ($context['reason'] ?? null) === 'not_authorized')
        ->once();
});

it('does not let a mentor read an unassigned mentee through subject_id', function () {
    $mentor = progressSecurityUser(['role' => 'mentor']);
    $mentor->assignRole('mentor');

    $stranger = progressSecurityUser();
    Objective::factory()->individual($stranger)->achieved()->create();

    $this->actingAs($mentor)
        ->getJson('/api/v1/progress/panel?subject_id='.$stranger->id)
        ->assertNotFound();
});

it('forbids an admin without the explicit support permission', function () {
    $admin = progressSecurityUser(['role' => 'admin']);
    $admin->assignRole('admin');

    $target = progressSecurityUser();

    $this->actingAs($admin)
        ->getJson("/api/v1/admin/progress/panel?user_id={$target->id}")
        ->assertForbidden();
});

it('lets an admin with the support permission read the panel and audits the access', function () {
    $admin = progressSecurityUser(['role' => 'admin']);
    $admin->assignRole('admin');
    $admin->givePermissionTo($this->supportPermission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $target = progressSecurityUser();
    Objective::factory()->individual($target)->achieved()->create();

    Log::spy();

    $this->actingAs($admin)
        ->getJson("/api/v1/admin/progress/panel?user_id={$target->id}")
        ->assertOk()
        ->assertJsonPath('data.viewer_role', 'support')
        ->assertJsonPath('data.subject.id', $target->id);

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message, $context = []) => $message === 'progress.support.view'
            && ($context['user_id'] ?? null) === $admin->id
            && ($context['subject_id'] ?? null) === $target->id)
        ->once();
});

it('does not let a non-mentor reach the mentor progress endpoints', function () {
    $mentee = progressSecurityUser();
    $other = progressSecurityUser();

    $this->actingAs($mentee)
        ->getJson("/api/v1/mentor/users/{$other->id}/progress")
        ->assertForbidden();
});

it('does not disclose a collective aggregate to an outsider who manipulates the id', function () {
    $manager = progressSecurityUser();
    $cohort = Cohort::create([
        'created_by' => $manager->id,
        'name' => 'Cohorte segura',
        'status' => 'active',
    ]);

    foreach (range(1, 3) as $i) {
        $member = progressSecurityUser();
        CohortMember::create([
            'cohort_id' => $cohort->id,
            'user_id' => $member->id,
            'status' => 'active',
        ]);
        Objective::factory()->individual($member)->achieved()->create();
    }

    $outsider = progressSecurityUser();

    $this->actingAs($outsider)
        ->getJson("/api/v1/progress/collectives/cohort/{$cohort->id}")
        ->assertForbidden();

    // Authorization happens before existence, so an unknown id is denied too.
    $this->actingAs($outsider)
        ->getJson('/api/v1/progress/collectives/cohort/'.Str::uuid())
        ->assertForbidden();
});
