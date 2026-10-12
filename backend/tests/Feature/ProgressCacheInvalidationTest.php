<?php

use App\Models\Challenge;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\MentorshipRelationship;
use App\Models\Objective;
use App\Models\PersonalityAssessment;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

uses()->group('progress', 'cache');

function progressCacheUser(array $attributes = []): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->create(array_merge([
        'client_type' => 'personal',
        'is_active' => true,
    ], $attributes)));
}

function progressCacheKey(User|string $user): string
{
    $id = $user instanceof User ? $user->id : $user;

    return 'progress:panel:'.$id.':'.now()->format('Y-m');
}

it('forgets the owner panel when an individual objective is saved', function () {
    $user = progressCacheUser();

    Cache::spy();

    Objective::factory()->individual($user)->create();

    Cache::shouldHaveReceived('forget')->with(progressCacheKey($user))->once();
});

it('forgets every member panel when a collective objective is saved', function () {
    $manager = progressCacheUser();
    $cohort = Cohort::create([
        'created_by' => $manager->id,
        'name' => 'Cohorte de caché',
        'status' => 'active',
    ]);

    $members = collect(range(1, 2))->map(function () use ($cohort) {
        $member = progressCacheUser();
        CohortMember::create([
            'cohort_id' => $cohort->id,
            'user_id' => $member->id,
            'status' => 'active',
        ]);

        return $member;
    });

    Cache::spy();

    Objective::factory()->collective('cohort', $cohort->id)->create();

    foreach ($members as $member) {
        Cache::shouldHaveReceived('forget')->with(progressCacheKey($member))->once();
    }
});

it('forgets the subject panel when a session is saved', function () {
    $user = progressCacheUser();
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $user->id,
    ]);

    Cache::spy();

    Session::factory()->create(['relationship_id' => $relationship->id]);

    Cache::shouldHaveReceived('forget')->with(progressCacheKey($user))->once();
});

it('forgets the mentee panel when a challenge is saved', function () {
    $mentee = progressCacheUser();

    Cache::spy();

    Challenge::factory()->create(['mentee_id' => $mentee->id]);

    Cache::shouldHaveReceived('forget')->with(progressCacheKey($mentee))->once();
});

it('forgets the user panel when the personality assessment is saved', function () {
    $user = progressCacheUser();

    Cache::spy();

    PersonalityAssessment::factory()->create(['user_id' => $user->id]);

    Cache::shouldHaveReceived('forget')->with(progressCacheKey($user))->once();
});
