<?php

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\PersonalityAssessment;
use App\Models\Plan;
use App\Models\Session;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MentorAssignmentService;
use App\Actions\CreateRelationshipAction;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses()->group('mentor-dashboard');

beforeEach(function () {
    Role::findOrCreate('mentor');
    Role::findOrCreate('mentee');

    $this->mentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
        'client_type' => 'personal',
    ]));
    $this->mentor->assignRole('mentor');

    $this->relationship = function (User $mentor, string $type, string $sourceType, string $sourceId) {
        return MentorshipRelationship::factory()->create([
            'mentor_id' => $mentor->id,
            'type' => $type,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => 'active',
        ]);
    };
});

function dashboardPlan(): Plan
{
    return Plan::create([
        'code' => 'dashboard_personal',
        'client_type' => 'personal',
        'name' => ['es' => 'Dashboard personal'],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_mentors' => 1,
        'entitlements' => [
            'mentor_workspace' => true,
            'ocean' => true,
        ],
        'is_active' => true,
    ]);
}

function subscribeDashboardUser(User $user, Plan $plan): void
{
    Subscription::create([
        'subscriber_type' => 'user',
        'subscriber_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);
}

it('requires the mentor role and lists only safe active personal assignments', function () {
    $target = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'name' => 'Assigned',
        'last_name' => 'Target',
        'email' => 'private-target@example.com',
        'birth_date' => '1990-01-01',
    ]));
    $inactiveTarget = User::withoutSyncingToSearch(fn () => User::factory()->create(['is_active' => false]));
    $otherTarget = User::withoutSyncingToSearch(fn () => User::factory()->create());
    ($this->relationship)($this->mentor, 'personal', 'user', $target->id);
    ($this->relationship)($this->mentor, 'personal', 'user', $inactiveTarget->id);
    ($this->relationship)($this->mentor, 'personal', 'company', $otherTarget->id);

    $response = $this->actingAs($this->mentor)
        ->getJson('/api/v1/mentor/users?search=assigned&per_page=100');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id)
        ->assertJsonPath('data.0.assignments.0.context.type', 'user')
        ->assertJsonMissingPath('data.0.email')
        ->assertJsonMissingPath('data.0.birth_date')
        ->assertJsonMissingPath('data.0.personality_assessment');

    $mentee = User::withoutSyncingToSearch(fn () => User::factory()->create());
    $mentee->assignRole('mentee');
    $this->actingAs($mentee)->getJson('/api/v1/mentor/users')->assertForbidden();
    $this->actingAs($this->mentor)->getJson('/api/v1/mentor/users?per_page=101')->assertUnprocessable();
});

it('filters plan tiers before pagination and reports counts for the full result set', function () {
    $basicUser = User::withoutSyncingToSearch(fn () => User::factory()->create(['name' => 'Basic User']));
    $premiumUser = User::withoutSyncingToSearch(fn () => User::factory()->create(['name' => 'Premium User']));
    $lockedUser = User::withoutSyncingToSearch(fn () => User::factory()->create(['name' => 'Locked User']));
    $basicRelationship = ($this->relationship)($this->mentor, 'personal', 'user', $basicUser->id);
    ($this->relationship)($this->mentor, 'personal', 'user', $premiumUser->id);
    ($this->relationship)($this->mentor, 'personal', 'user', $lockedUser->id);
    subscribeDashboardUser($basicUser, Plan::create([
        'code' => 'personal_basic',
        'client_type' => 'personal',
        'name' => ['es' => 'Personal Básico'],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_mentors' => 1,
        'entitlements' => ['mentor_workspace' => true, 'ocean' => true],
        'is_active' => true,
    ]));
    subscribeDashboardUser($premiumUser, Plan::create([
        'code' => 'personal_premium',
        'client_type' => 'personal',
        'name' => ['es' => 'Personal Premium'],
        'price_monthly' => 20,
        'price_yearly' => 200,
        'max_mentors' => 1,
        'entitlements' => ['mentor_workspace' => true, 'ocean' => true],
        'is_active' => true,
    ]));

    $response = $this->actingAs($this->mentor)->getJson('/api/v1/mentor/users?plan_filter=basic&per_page=1');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $basicUser->id)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('plan_counts.all', 3)
        ->assertJsonPath('plan_counts.basic', 1)
        ->assertJsonPath('plan_counts.premium', 1)
        ->assertJsonPath('plan_counts.locked', 1);
    expect($basicRelationship)->not->toBeNull();
});

it('rejects non-canonical sources and mentors with a different client type on creation', function () {
    $source = User::withoutSyncingToSearch(fn () => User::factory()->create());
    $action = app(CreateRelationshipAction::class);

    expect(fn () => $action->execute([
        'type' => 'personal',
        'source_type' => 'company',
        'source_id' => $source->id,
        'mentor_id' => $this->mentor->id,
    ]))->toThrow(ValidationException::class);

    $businessMentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
        'client_type' => 'empresa',
    ]));

    expect(fn () => $action->execute([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $source->id,
        'mentor_id' => $businessMentor->id,
    ]))->toThrow(ValidationException::class);
});

it('resolves family cohort and company users while excluding inactive memberships', function () {
    $resolvedIds = [];

    $familyMentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create(['client_type' => 'familiar']));
    $familyHead = User::withoutSyncingToSearch(fn () => User::factory()->create(['client_type' => 'familiar']));
    $familyMember = User::withoutSyncingToSearch(fn () => User::factory()->create(['client_type' => 'familiar']));
    $inactiveFamilyMember = User::withoutSyncingToSearch(fn () => User::factory()->create(['client_type' => 'familiar', 'is_active' => false]));
    $family = FamilyGroup::create(['head_user_id' => $familyHead->id, 'family_name' => 'Rivera']);
    FamilyMember::create(['family_group_id' => $family->id, 'user_id' => $familyMember->id, 'full_name' => $familyMember->full_name]);
    FamilyMember::create(['family_group_id' => $family->id, 'user_id' => $inactiveFamilyMember->id, 'full_name' => $inactiveFamilyMember->full_name]);
    FamilyMember::create(['family_group_id' => $family->id, 'full_name' => 'Unregistered child']);
    ($this->relationship)($familyMentor, 'familiar', 'family_group', $family->id);
    $familyIds = app(MentorAssignmentService::class)->assignedUsers($familyMentor)->pluck('user.id')->all();
    expect($familyIds)->toContain($familyHead->id, $familyMember->id)->not->toContain($inactiveFamilyMember->id);
    $resolvedIds = array_merge($resolvedIds, $familyIds);

    $groupMentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create(['client_type' => 'grupal']));
    $activeCohortUser = User::withoutSyncingToSearch(fn () => User::factory()->create(['client_type' => 'grupal']));
    $droppedCohortUser = User::withoutSyncingToSearch(fn () => User::factory()->create(['client_type' => 'grupal']));
    $cohort = Cohort::create(['name' => 'Leaders', 'status' => 'active']);
    CohortMember::create(['cohort_id' => $cohort->id, 'user_id' => $activeCohortUser->id, 'status' => 'active']);
    CohortMember::create(['cohort_id' => $cohort->id, 'user_id' => $droppedCohortUser->id, 'status' => 'dropped']);
    ($this->relationship)($groupMentor, 'grupal', 'cohort', $cohort->id);
    $cohortIds = app(MentorAssignmentService::class)->assignedUsers($groupMentor)->pluck('user.id')->all();
    expect($cohortIds)->toBe([$activeCohortUser->id]);
    $resolvedIds = array_merge($resolvedIds, $cohortIds);

    $companyMentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create(['client_type' => 'empresa']));
    $companyAdmin = User::withoutSyncingToSearch(fn () => User::factory()->create(['client_type' => 'empresa']));
    $activeEmployee = User::withoutSyncingToSearch(fn () => User::factory()->create(['client_type' => 'empresa']));
    $inactiveEmployee = User::withoutSyncingToSearch(fn () => User::factory()->create(['client_type' => 'empresa']));
    $company = Company::create(['legal_name' => 'MIS Corp', 'admin_id' => $companyAdmin->id]);
    Employee::create(['company_id' => $company->id, 'user_id' => $activeEmployee->id, 'status' => 'active']);
    Employee::create(['company_id' => $company->id, 'user_id' => $inactiveEmployee->id, 'status' => 'inactive']);
    ($this->relationship)($companyMentor, 'empresa', 'company', $company->id);
    $companyIds = app(MentorAssignmentService::class)->assignedUsers($companyMentor)->pluck('user.id')->all();
    expect($companyIds)->toContain($companyAdmin->id, $activeEmployee->id)->not->toContain($inactiveEmployee->id);

    expect($resolvedIds)->not->toBeEmpty();
});

it('requires applicable active plans and deduplicates users while retaining assignments', function () {
    $mentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create(['client_type' => 'grupal']));
    $target = User::withoutSyncingToSearch(fn () => User::factory()->create(['client_type' => 'grupal']));
    $validPlan = Plan::create([
        'client_type' => 'grupal',
        'name' => ['es' => 'Grupal'],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'is_active' => true,
    ]);
    $invalidPlan = Plan::create([
        'client_type' => 'personal',
        'name' => ['es' => 'Personal'],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'is_active' => true,
    ]);
    $valid = Cohort::create(['name' => 'Valid', 'status' => 'active', 'plan_id' => $validPlan->id]);
    $legacy = Cohort::create(['name' => 'Legacy', 'status' => 'active']);
    $invalid = Cohort::create(['name' => 'Invalid', 'status' => 'active', 'plan_id' => $invalidPlan->id]);

    foreach ([$valid, $legacy, $invalid] as $cohort) {
        CohortMember::create(['cohort_id' => $cohort->id, 'user_id' => $target->id, 'status' => 'active']);
        ($this->relationship)($mentor, 'grupal', 'cohort', $cohort->id);
    }

    $users = app(MentorAssignmentService::class)->assignedUsers($mentor);

    expect($users)->toHaveCount(1)
        ->and($users->first()['assignments'])->toHaveCount(2)
        ->and(collect($users->first()['assignments'])->pluck('context.name')->all())->toContain('Valid', 'Legacy')->not->toContain('Invalid');
});

it('returns target-specific safe OCEAN data and hides unauthorized pairs as not found', function () {
    $target = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'email' => 'secret@example.com',
        'birth_date' => '1980-04-05',
    ]));
    $relationship = ($this->relationship)($this->mentor, 'personal', 'user', $target->id);
    subscribeDashboardUser($target, dashboardPlan());
    PersonalityAssessment::factory()->create([
        'user_id' => $target->id,
        'answers' => ['secret-answer'],
        'raw_scores' => ['secret-score'],
        'results' => [
            'factors' => ['O' => 81, 'C' => 72, 'E' => 63, 'A' => 54, 'N' => 25],
            'facets' => ['imagination' => 88],
        ],
    ]);

    $response = $this->actingAs($this->mentor)->getJson(
        "/api/v1/mentor/users/{$target->id}/personality?relationship_id={$relationship->id}"
    );

    $response->assertOk()
        ->assertJsonPath('assessment.factors.O', 81)
        ->assertJsonPath('assessment.facets.imagination', 88)
        ->assertJsonPath('assessment.interpretation.O.score', 81)
        ->assertJsonMissingPath('user.email')
        ->assertJsonMissingPath('user.birth_date')
        ->assertJsonMissingPath('assessment.answers')
        ->assertJsonMissingPath('assessment.raw_scores');

    $otherMentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create(['client_type' => 'personal']));
    $otherMentor->assignRole('mentor');
    $this->actingAs($otherMentor)
        ->getJson("/api/v1/mentor/users/{$target->id}/personality?relationship_id={$relationship->id}")
        ->assertNotFound();

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$target->id}/personality?relationship_id=not-a-uuid")
        ->assertUnprocessable();
});

it('builds local recommendations for the target and limits sessions to the authorized relationship', function () {
    $target = User::withoutSyncingToSearch(fn () => User::factory()->create());
    $otherTarget = User::withoutSyncingToSearch(fn () => User::factory()->create());
    $compatibleMentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create(['client_type' => 'personal']));
    $wrongTypeMentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create(['client_type' => 'empresa']));
    $targetOcean = ['O' => 88, 'C' => 77, 'E' => 66, 'A' => 55, 'N' => 22];
    PersonalityAssessment::factory()->create(['user_id' => $target->id, 'results' => ['factors' => $targetOcean]]);
    PersonalityAssessment::factory()->create(['user_id' => $compatibleMentor->id, 'results' => ['factors' => $targetOcean]]);
    PersonalityAssessment::factory()->create(['user_id' => $wrongTypeMentor->id, 'results' => ['factors' => $targetOcean]]);
    PersonalityAssessment::factory()->create(['user_id' => $this->mentor->id, 'results' => ['factors' => ['O' => 20, 'C' => 20, 'E' => 20, 'A' => 20, 'N' => 80]]]);

    $relationship = ($this->relationship)($this->mentor, 'personal', 'user', $target->id);
    subscribeDashboardUser($target, dashboardPlan());
    $otherRelationship = ($this->relationship)($this->mentor, 'personal', 'user', $otherTarget->id);
    $authorizedSession = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'meet_link' => 'https://meet.example/private',
        'mentor_notes' => 'private notes',
        'scheduled_at' => now()->addDay(),
    ]);
    Session::factory()->create(['relationship_id' => $otherRelationship->id, 'scheduled_at' => now()->addDay()]);

    $response = $this->actingAs($this->mentor)->getJson(
        "/api/v1/mentor/users/{$target->id}/recommendations?relationship_id={$relationship->id}"
    );

    $response->assertOk()
        ->assertJsonPath('recommended_mentors.0.id', $compatibleMentor->id)
        ->assertJsonPath('recommended_sessions.0.id', $authorizedSession->id)
        ->assertJsonCount(1, 'recommended_sessions')
        ->assertJsonMissing(['id' => $wrongTypeMentor->id])
        ->assertJsonMissingPath('recommended_mentors.0.email')
        ->assertJsonMissingPath('recommended_mentors.0.personality_assessment')
        ->assertJsonMissingPath('recommended_sessions.0.meet_link')
        ->assertJsonMissingPath('recommended_sessions.0.mentor_notes')
        ->assertJsonMissingPath('recommended_sessions.0.attendees')
        ->assertJsonMissingPath('user_ocean');

    $incomplete = User::withoutSyncingToSearch(fn () => User::factory()->create());
    $incompleteRelationship = ($this->relationship)($this->mentor, 'personal', 'user', $incomplete->id);
    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$incomplete->id}/recommendations?relationship_id={$incompleteRelationship->id}")
        ->assertOk()
        ->assertJsonMissingPath('user_ocean')
        ->assertJsonStructure(['recommended_mentors', 'recommended_sessions', 'recommended_evaluations']);
});
