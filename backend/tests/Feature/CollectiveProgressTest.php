<?php

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Objective;
use App\Models\User;

uses()->group('progress', 'collective');

function progressUser(): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->create([
        'client_type' => 'personal',
        'is_active' => true,
    ]));
}

function progressCohort(User $manager): Cohort
{
    return Cohort::create([
        'created_by' => $manager->id,
        'name' => 'Cohorte '.fake()->word(),
        'status' => 'active',
    ]);
}

function progressJoin(Cohort $cohort, User $member): void
{
    CohortMember::create([
        'cohort_id' => $cohort->id,
        'user_id' => $member->id,
        'status' => 'active',
    ]);
}

it('shows the aggregate with base for a manager when the base is large enough', function () {
    $manager = progressUser();
    $cohort = progressCohort($manager);

    foreach (range(1, 3) as $i) {
        $member = progressUser();
        progressJoin($cohort, $member);
        Objective::factory()->individual($member)->achieved()->create();
        Objective::factory()->individual($member)->create(['status' => 'in_progress']);
    }

    $this->actingAs($manager)
        ->getJson("/api/v1/progress/collectives/cohort/{$cohort->id}")
        ->assertOk()
        ->assertJsonPath('data.viewer_role', 'manager')
        ->assertJsonPath('data.suppressed', false)
        ->assertJsonPath('data.metrics.mean', 50)
        ->assertJsonPath('data.metrics.sum.achieved', 3)
        ->assertJsonPath('data.metrics.sum.total', 6)
        ->assertJsonPath('data.base.eligible_members', 3)
        ->assertJsonPath('data.base.contributing_members', 3)
        ->assertJsonMissingPath('data.members');
});

it('shows the same aggregate to a member without any person breakdown', function () {
    $manager = progressUser();
    $cohort = progressCohort($manager);

    $members = [];

    foreach (range(1, 3) as $i) {
        $member = progressUser();
        $members[] = $member;
        progressJoin($cohort, $member);
        Objective::factory()->individual($member)->achieved()->create();
        Objective::factory()->individual($member)->create(['status' => 'in_progress']);
    }

    $this->actingAs($members[0])
        ->getJson("/api/v1/progress/collectives/cohort/{$cohort->id}")
        ->assertOk()
        ->assertJsonPath('data.viewer_role', 'member')
        ->assertJsonPath('data.metrics.mean', 50)
        ->assertJsonMissingPath('data.members')
        ->assertJsonMissingPath('data.metrics.members');
});

it('suppresses both manager and member when the base is below the threshold', function () {
    $manager = progressUser();
    $cohort = progressCohort($manager);

    $members = [];

    foreach (range(1, 2) as $i) {
        $member = progressUser();
        $members[] = $member;
        progressJoin($cohort, $member);
        Objective::factory()->individual($member)->achieved()->create();
    }

    foreach ([$manager, $members[0]] as $viewer) {
        $this->actingAs($viewer)
            ->getJson("/api/v1/progress/collectives/cohort/{$cohort->id}")
            ->assertOk()
            ->assertJsonPath('data.suppressed', true)
            ->assertJsonPath('data.suppression_reason', 'insufficient_base')
            ->assertJsonPath('data.metrics', null)
            ->assertJsonPath('data.base', null);
    }
});

it('returns no_data when nobody in the collective has objectives', function () {
    $manager = progressUser();
    $cohort = progressCohort($manager);

    foreach (range(1, 3) as $i) {
        progressJoin($cohort, progressUser());
    }

    $this->actingAs($manager)
        ->getJson("/api/v1/progress/collectives/cohort/{$cohort->id}")
        ->assertOk()
        ->assertJsonPath('data.state', 'no_data')
        ->assertJsonPath('data.suppressed', false)
        ->assertJsonPath('data.metrics', null);
});

it('denies a non-member without leaking data', function () {
    $manager = progressUser();
    $cohort = progressCohort($manager);

    $outsider = progressUser();

    $this->actingAs($outsider)
        ->getJson("/api/v1/progress/collectives/cohort/{$cohort->id}")
        ->assertForbidden();
});
