<?php

use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Illuminate\Support\Facades\DB;

uses()->group('evaluations');

function makeRelationship(User $mentor, string $type, string $sourceType, string $sourceId): MentorshipRelationship
{
    return MentorshipRelationship::factory()->create([
        'mentor_id' => $mentor->id,
        'type' => $type,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
        'status' => 'active',
    ]);
}

function makeResult(User $user, Assessment $assessment): void
{
    AssessmentResult::create([
        'user_id' => $user->id,
        'assessment_id' => $assessment->id,
        'answers' => [['question_id' => 1, 'option_index' => 1]],
        'score' => 80,
        'passed' => true,
        'time_spent_seconds' => 60,
        'completed_at' => now(),
    ]);
}

it('resolves users across all source types with a constant query count', function () {
    $mentor = User::factory()->mentor()->create(['client_type' => null]);
    $token = $mentor->createToken('test')->plainTextToken;

    $assessment = Assessment::create([
        'slug' => 'eval-query-test',
        'title' => ['es' => 'Evaluacion'],
        'questions' => [['id' => 1, 'options' => ['a', 'b']]],
        'is_active' => true,
    ]);

    $makeUsers = fn (string $clientType) => User::factory()->count(2)->create(['client_type' => $clientType]);

    // user-source relationship
    $soloUsers = $makeUsers('personal');
    makeRelationship($mentor, 'personal', 'user', $soloUsers[0]->id);

    // family
    $familyHead = $makeUsers('familiar')->first();
    $familyMemberUser = $makeUsers('familiar')->first();
    $family = FamilyGroup::create(['head_user_id' => $familyHead->id, 'family_name' => 'Q']);
    FamilyMember::create(['family_group_id' => $family->id, 'user_id' => $familyMemberUser->id, 'full_name' => 'Member']);
    makeRelationship($mentor, 'familiar', 'family_group', $family->id);

    // cohort
    $cohortUser = $makeUsers('grupal')->first();
    $cohort = Cohort::create(['name' => 'C1', 'status' => 'active']);
    CohortMember::create(['cohort_id' => $cohort->id, 'user_id' => $cohortUser->id, 'status' => 'active']);
    makeRelationship($mentor, 'grupal', 'cohort', $cohort->id);

    $expectedIds = collect([$soloUsers[0]->id, $familyHead->id, $familyMemberUser->id, $cohortUser->id]);
    foreach ($expectedIds as $id) {
        makeResult(User::find($id), $assessment);
    }

    $this->withToken($token)->getJson('/api/v1/mentor/evaluation-results?per_page=100')->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response3 = $this->withToken($token)->getJson('/api/v1/mentor/evaluation-results?per_page=100');
    $queries3 = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response3->assertOk();
    $ids3 = collect($response3->json('data'))->pluck('user.id');
    foreach ($expectedIds as $id) {
        expect($ids3)->toContain($id);
    }

    // Three more relationships of the remaining types: query count must not grow.
    $cohort2User = $makeUsers('grupal')->first();
    $cohort2 = Cohort::create(['name' => 'C2', 'status' => 'active']);
    CohortMember::create(['cohort_id' => $cohort2->id, 'user_id' => $cohort2User->id, 'status' => 'active']);
    makeRelationship($mentor, 'grupal', 'cohort', $cohort2->id);

    $companyAdmin = $makeUsers('empresa')->first();
    $employeeUser = $makeUsers('empresa')->first();
    $company = Company::create(['legal_name' => 'Q Inc', 'admin_id' => $companyAdmin->id]);
    Employee::create(['company_id' => $company->id, 'user_id' => $employeeUser->id, 'status' => 'active']);
    makeRelationship($mentor, 'empresa', 'company', $company->id);

    $solo2 = $makeUsers('personal')->first();
    makeRelationship($mentor, 'personal', 'user', $solo2->id);

    $moreIds = [$cohort2User->id, $companyAdmin->id, $employeeUser->id, $solo2->id];
    foreach ($moreIds as $id) {
        makeResult(User::find($id), $assessment);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response6 = $this->withToken($token)->getJson('/api/v1/mentor/evaluation-results?per_page=100');
    $queries6 = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response6->assertOk();
    $ids6 = collect($response6->json('data'))->pluck('user.id');
    foreach ($expectedIds->merge($moreIds) as $id) {
        expect($ids6)->toContain($id);
    }

    expect($queries6)->toBe($queries3);
});
