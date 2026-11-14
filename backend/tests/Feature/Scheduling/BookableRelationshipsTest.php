<?php

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\User;

uses()->group('scheduling', 'booking');

function bookableActor(array $attributes = []): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->create(array_merge([
        'client_type' => 'personal',
        'is_active' => true,
    ], $attributes)));
}

function personalRel(User $mentor, User $mentee, array $overrides = []): MentorshipRelationship
{
    return MentorshipRelationship::factory()->create(array_merge([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ], $overrides));
}

function familyRel(User $mentor, User $head, array $overrides = []): array
{
    $family = FamilyGroup::create(['head_user_id' => $head->id, 'family_name' => 'Bookable Family']);
    FamilyMember::create(['family_group_id' => $family->id, 'user_id' => $head->id, 'full_name' => $head->name, 'relationship' => 'parent']);
    $rel = MentorshipRelationship::factory()->create(array_merge([
        'type' => 'familiar',
        'source_type' => 'family_group',
        'source_id' => $family->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ], $overrides));

    return [$rel, $family];
}

function cohortRel(User $mentor, User $manager, array $overrides = []): array
{
    $cohort = Cohort::create(['created_by' => $manager->id, 'name' => 'Bookable cohort', 'status' => 'active']);
    $rel = MentorshipRelationship::factory()->create(array_merge([
        'type' => 'grupal',
        'source_type' => 'cohort',
        'source_id' => $cohort->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ], $overrides));

    return [$rel, $cohort];
}

function companyRel(User $mentor, User $admin, array $overrides = []): array
{
    $company = Company::create([
        'admin_id' => $admin->id,
        'legal_name' => 'Bookable Co',
        'tax_id' => fake()->unique()->numerify('########'),
        'industry' => 'Education',
        'size' => 'small',
    ]);
    $rel = MentorshipRelationship::factory()->create(array_merge([
        'type' => 'empresa',
        'source_type' => 'company',
        'source_id' => $company->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ], $overrides));

    return [$rel, $company];
}

function bookableIds(\Illuminate\Testing\TestResponse $response): array
{
    return collect($response->json('data'))->pluck('id')->all();
}

it('requires authentication', function () {
    $this->getJson('/api/v1/sessions/bookable-relationships')->assertUnauthorized();
});

it('returns the exact contract shape for each entry', function () {
    $mentor = bookableActor(['role' => 'mentor']);
    personalRel($mentor, bookableActor());

    $response = $this->actingAs($mentor)->getJson('/api/v1/sessions/bookable-relationships');

    $response->assertOk()->assertJsonCount(1, 'data');
    $entry = $response->json('data.0');
    expect(array_keys($entry))->toBe(['id', 'type', 'session_type', 'is_collective', 'mentor', 'source']);
    expect(array_keys($entry['mentor']))->toBe(['id', 'full_name']);
    expect(array_keys($entry['source']))->toBe(['type', 'name']);
});

it('lists a mentor own active relationships with the right session_type per type', function () {
    $mentor = bookableActor(['role' => 'mentor']);
    $personal = personalRel($mentor, bookableActor());
    [$family] = familyRel($mentor, bookableActor());
    [$cohort] = cohortRel($mentor, bookableActor());
    [$company] = companyRel($mentor, bookableActor());

    $response = $this->actingAs($mentor)->getJson('/api/v1/sessions/bookable-relationships')->assertOk();

    $byId = collect($response->json('data'))->keyBy('id');
    expect($byId->keys()->all())
        ->toContain($personal->id, $family->id, $cohort->id, $company->id)
        ->toHaveCount(4);
    expect($byId[$personal->id]['session_type'])->toBe('individual');
    expect($byId[$family->id]['session_type'])->toBe('family');
    expect($byId[$cohort->id]['session_type'])->toBe('group');
    expect($byId[$company->id]['session_type'])->toBe('corporate');
    expect($byId[$personal->id]['is_collective'])->toBeFalse();
    expect($byId[$family->id]['is_collective'])->toBeTrue();
    expect($byId[$company->id]['is_collective'])->toBeTrue();
    expect($byId[$company->id]['source']['name'])->toBe('Bookable Co');
    expect($byId[$family->id]['source']['name'])->toBe('Bookable Family');
    expect($byId[$personal->id]['mentor']['full_name'])->toBe($mentor->full_name);
});

it('lets an individual client book their own relationship', function () {
    $mentee = bookableActor();
    $rel = personalRel(bookableActor(['role' => 'mentor']), $mentee);

    $response = $this->actingAs($mentee)->getJson('/api/v1/sessions/bookable-relationships')->assertOk();

    expect(bookableIds($response))->toBe([$rel->id]);
});

it('lets the collective responsible book but excludes a non-responsible member', function (string $kind) {
    $responsible = bookableActor();
    $member = bookableActor();
    $mentor = bookableActor(['role' => 'mentor']);

    if ($kind === 'family') {
        [$rel, $source] = familyRel($mentor, $responsible);
        FamilyMember::create(['family_group_id' => $source->id, 'user_id' => $member->id, 'full_name' => $member->name, 'relationship' => 'child']);
    } elseif ($kind === 'cohort') {
        [$rel, $source] = cohortRel($mentor, $responsible);
        CohortMember::create(['cohort_id' => $source->id, 'user_id' => $member->id, 'status' => 'active']);
    } else {
        [$rel, $source] = companyRel($mentor, $responsible);
        Employee::create(['company_id' => $source->id, 'user_id' => $member->id, 'status' => 'active']);
    }

    expect(bookableIds($this->actingAs($responsible)->getJson('/api/v1/sessions/bookable-relationships')->assertOk()))
        ->toBe([$rel->id]);
    expect(bookableIds($this->actingAs($member)->getJson('/api/v1/sessions/bookable-relationships')->assertOk()))
        ->not->toContain($rel->id);
})->with(['family', 'cohort', 'company']);

it('excludes relationships that are not active', function (string $status) {
    $mentor = bookableActor(['role' => 'mentor']);
    $rel = personalRel($mentor, bookableActor(), ['status' => $status]);

    expect(bookableIds($this->actingAs($mentor)->getJson('/api/v1/sessions/bookable-relationships')->assertOk()))
        ->not->toContain($rel->id);
})->with(['paused', 'completed', 'canceled', 'pending']);

it('does not list other people relationships', function () {
    $outsider = bookableActor();
    $rel = personalRel(bookableActor(['role' => 'mentor']), bookableActor());

    expect(bookableIds($this->actingAs($outsider)->getJson('/api/v1/sessions/bookable-relationships')->assertOk()))
        ->not->toContain($rel->id);
});

it('returns an empty list for admins regardless of support permission, with no relationship queries', function (?string $permission) {
    $admin = bookableActor(['role' => 'admin', 'client_type' => null]);
    if ($permission) {
        $admin->givePermissionTo(\Spatie\Permission\Models\Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }
    personalRel(bookableActor(['role' => 'mentor']), bookableActor());

    \Illuminate\Support\Facades\DB::enableQueryLog();
    $response = $this->actingAs($admin)->getJson('/api/v1/sessions/bookable-relationships')->assertOk();
    $queries = count(\Illuminate\Support\Facades\DB::getQueryLog());
    \Illuminate\Support\Facades\DB::disableQueryLog();

    expect($response->json('data'))->toBe([]);
    expect($queries)->toBeLessThanOrEqual(2);
})->with([null, 'scheduling.manage.support']);

it('includes an active relationship on a cohort the user created without being a member', function () {
    $creator = bookableActor();
    $mentor = bookableActor(['role' => 'mentor']);
    [$rel, $cohort] = cohortRel($mentor, $creator);
    // someone else is the member; the creator only manages the cohort
    CohortMember::create(['cohort_id' => $cohort->id, 'user_id' => bookableActor()->id, 'status' => 'active']);

    $response = $this->actingAs($creator)->getJson('/api/v1/sessions/bookable-relationships')->assertOk();

    expect(bookableIds($response))->toBe([$rel->id]);
    expect($response->json('data.0.session_type'))->toBe('group');
    expect($response->json('data.0.is_collective'))->toBeTrue();

    // coherence: POST /sessions accepts the booking on that relationship
    $this->actingAs($creator)->postJson('/api/v1/sessions', [
        'relationship_id' => $rel->id,
        'session_type' => 'group',
        'title' => 'Cohort creator booking',
        'scheduled_at' => now()->addDays(6)->startOfHour()->toIso8601String(),
        'duration_minutes' => 60,
    ])->assertCreated();
});

it('coheres with POST /sessions: listed relationships accept a booking', function () {
    $mentor = bookableActor(['role' => 'mentor']);
    $personal = personalRel($mentor, bookableActor());
    [$family] = familyRel($mentor, bookableActor());

    $response = $this->actingAs($mentor)->getJson('/api/v1/sessions/bookable-relationships')->assertOk();
    $entries = collect($response->json('data'))->keyBy('id');

    foreach ([$personal, $family] as $i => $rel) {
        $this->actingAs($mentor)->postJson('/api/v1/sessions', [
            'relationship_id' => $rel->id,
            'session_type' => $entries[$rel->id]['session_type'],
            'title' => "Coherence booking {$i}",
            'scheduled_at' => now()->addDays(4 + $i)->startOfHour()->toIso8601String(),
            'duration_minutes' => 60,
        ])->assertCreated();
    }
});

it('coheres with POST /sessions: excluded-but-visible membership and inactive relationships are rejected', function () {
    $mentor = bookableActor(['role' => 'mentor']);
    $head = bookableActor();
    $member = bookableActor();
    [$activeRel, $family] = familyRel($mentor, $head);
    FamilyMember::create(['family_group_id' => $family->id, 'user_id' => $member->id, 'full_name' => $member->name, 'relationship' => 'child']);
    $pausedRel = personalRel($mentor, bookableActor(), ['status' => 'paused']);

    // The member sees the relationship (canSee) but cannot create: 403.
    $this->actingAs($member)->postJson('/api/v1/sessions', [
        'relationship_id' => $activeRel->id,
        'session_type' => 'family',
        'title' => 'Member booking attempt',
        'scheduled_at' => now()->addDays(5)->startOfHour()->toIso8601String(),
        'duration_minutes' => 60,
    ])->assertForbidden();

    // An inactive relationship passes the gate for its participants but the
    // service rejects it as relationship_inactive on relationship_id.
    $this->actingAs($mentor)->postJson('/api/v1/sessions', [
        'relationship_id' => $pausedRel->id,
        'session_type' => 'individual',
        'title' => 'Paused booking attempt',
        'scheduled_at' => now()->addDays(5)->startOfHour()->toIso8601String(),
        'duration_minutes' => 60,
    ])->assertUnprocessable()->assertJsonValidationErrors('relationship_id');
});
