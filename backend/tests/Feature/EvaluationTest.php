<?php

use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

uses()->group('evaluations');

function evaluationPayload(array $overrides = []): array
{
    return array_merge([
        'slug' => fake()->unique()->slug(),
        'title' => ['es' => 'Evaluacion de prueba'],
        'description' => ['es' => 'Descripcion de prueba'],
        'category' => 'bienestar',
        'client_types' => ['personal'],
        'questions' => [
            [
                'id' => 1,
                'text' => 'Pregunta 1',
                'options' => ['Nunca', 'A veces', 'Siempre'],
                'dimension' => 'base',
                'reverse_score' => false,
            ],
            [
                'id' => 2,
                'text' => 'Pregunta 2',
                'options' => ['Nunca', 'A veces', 'Siempre'],
                'dimension' => 'base',
                'reverse_score' => false,
            ],
        ],
        'passing_score' => 70,
        'time_limit_minutes' => 10,
        'is_active' => true,
    ], $overrides);
}

function createUserWithoutSearch(array $attributes = []): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->create($attributes));
}

function createSuperAdminWithoutSearch(): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->superAdmin()->create());
}

it('lists active evaluations for the authenticated user client type', function () {
    $user = createUserWithoutSearch(['client_type' => 'personal']);
    $token = $user->createToken('test')->plainTextToken;

    $personal = Assessment::create(evaluationPayload(['slug' => 'personal-evaluation']));
    Assessment::create(evaluationPayload(['slug' => 'business-evaluation', 'client_types' => ['empresa']]));
    Assessment::create(evaluationPayload(['slug' => 'inactive-evaluation', 'is_active' => false]));

    $response = $this->withToken($token)->getJson('/api/v1/evaluations');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $personal->id);
});

it('stores and validates an evaluation result for the user', function () {
    $user = createUserWithoutSearch(['client_type' => 'personal']);
    $token = $user->createToken('test')->plainTextToken;
    $evaluation = Assessment::create(evaluationPayload());

    $response = $this->withToken($token)->postJson("/api/v1/evaluations/{$evaluation->id}/submit", [
        'answers' => [
            ['question_id' => 1, 'option_index' => 2],
            ['question_id' => 2, 'option_index' => 2],
        ],
        'time_spent_seconds' => 90,
    ]);

    $response->assertCreated()
        ->assertJsonPath('score', 100)
        ->assertJsonPath('passed', true);

    $this->assertDatabaseHas('assessment_results', [
        'user_id' => $user->id,
        'assessment_id' => $evaluation->id,
        'passed' => true,
    ]);
});

it('shows an accessible evaluation directly with the user result', function () {
    $user = createUserWithoutSearch(['client_type' => 'personal']);
    $token = $user->createToken('test')->plainTextToken;
    $evaluation = Assessment::create(evaluationPayload());

    AssessmentResult::create([
        'user_id' => $user->id,
        'assessment_id' => $evaluation->id,
        'answers' => [
            ['question_id' => 1, 'option_index' => 2],
            ['question_id' => 2, 'option_index' => 2],
        ],
        'score' => 100,
        'passed' => true,
        'time_spent_seconds' => 90,
        'completed_at' => now(),
    ]);

    $this->withToken($token)
        ->getJson("/api/v1/evaluations/{$evaluation->id}")
        ->assertOk()
        ->assertJsonPath('id', $evaluation->id)
        ->assertJsonPath('user_result.passed', true);
});

it('rejects incomplete evaluation answers', function () {
    $user = createUserWithoutSearch(['client_type' => 'personal']);
    $token = $user->createToken('test')->plainTextToken;
    $evaluation = Assessment::create(evaluationPayload());

    $response = $this->withToken($token)->postJson("/api/v1/evaluations/{$evaluation->id}/submit", [
        'answers' => [
            ['question_id' => 1, 'option_index' => 2],
        ],
    ]);

    $response->assertUnprocessable();
});

it('allows admins to list evaluation results', function () {
    $this->seed(RolePermissionSeeder::class);

    $admin = createSuperAdminWithoutSearch();
    $admin->assignRole('super_admin');
    $token = $admin->createToken('test')->plainTextToken;
    $user = createUserWithoutSearch(['client_type' => 'personal']);
    $evaluation = Assessment::create(evaluationPayload());

    AssessmentResult::create([
        'user_id' => $user->id,
        'assessment_id' => $evaluation->id,
        'answers' => [
            ['question_id' => 1, 'option_index' => 2],
            ['question_id' => 2, 'option_index' => 2],
        ],
        'score' => 100,
        'passed' => true,
        'time_spent_seconds' => 90,
        'completed_at' => now(),
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/admin/evaluation-results')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user.id', $user->id)
        ->assertJsonPath('data.0.assessment.id', $evaluation->id);
});

it('allows mentors to list evaluation results for related users', function () {
    $mentor = createUserWithoutSearch(['role' => 'mentor', 'client_type' => 'personal']);
    $mentee = createUserWithoutSearch(['client_type' => 'personal']);
    $differentClientTypeMentee = createUserWithoutSearch(['client_type' => 'empresa']);
    $unrelated = createUserWithoutSearch(['client_type' => 'personal']);
    $token = $mentor->createToken('test')->plainTextToken;
    $evaluation = Assessment::create(evaluationPayload());

    $relationship = MentorshipRelationship::create([
        'type' => 'personal',
        'source_type' => User::class,
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
        'started_at' => now(),
    ]);

    AssessmentResult::create([
        'user_id' => $mentee->id,
        'assessment_id' => $evaluation->id,
        'answers' => [
            ['question_id' => 1, 'option_index' => 2],
            ['question_id' => 2, 'option_index' => 2],
        ],
        'score' => 100,
        'passed' => true,
        'time_spent_seconds' => 90,
        'completed_at' => now(),
    ]);

    MentorshipRelationship::create([
        'type' => 'empresa',
        'source_type' => User::class,
        'source_id' => $differentClientTypeMentee->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
        'started_at' => now(),
    ]);

    AssessmentResult::create([
        'user_id' => $differentClientTypeMentee->id,
        'assessment_id' => $evaluation->id,
        'answers' => [
            ['question_id' => 1, 'option_index' => 2],
            ['question_id' => 2, 'option_index' => 2],
        ],
        'score' => 100,
        'passed' => true,
        'time_spent_seconds' => 90,
        'completed_at' => now(),
    ]);

    AssessmentResult::create([
        'user_id' => $unrelated->id,
        'assessment_id' => $evaluation->id,
        'answers' => [
            ['question_id' => 1, 'option_index' => 1],
            ['question_id' => 2, 'option_index' => 1],
        ],
        'score' => 50,
        'passed' => false,
        'time_spent_seconds' => 120,
        'completed_at' => now(),
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/mentor/evaluation-results')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user.id', $mentee->id)
        ->assertJsonPath('data.0.assessment.id', $evaluation->id)
        ->assertJsonPath('data.0.relationship.id', $relationship->id);
});

it('does not expose evaluation results from pending mentor relationships', function () {
    $mentor = createUserWithoutSearch(['role' => 'mentor']);
    $mentee = createUserWithoutSearch(['client_type' => 'personal']);
    $token = $mentor->createToken('test')->plainTextToken;
    $evaluation = Assessment::create(evaluationPayload());

    MentorshipRelationship::create([
        'type' => 'personal',
        'source_type' => User::class,
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
        'status' => 'pending',
    ]);

    AssessmentResult::create([
        'user_id' => $mentee->id,
        'assessment_id' => $evaluation->id,
        'answers' => [
            ['question_id' => 1, 'option_index' => 2],
            ['question_id' => 2, 'option_index' => 2],
        ],
        'score' => 100,
        'passed' => true,
        'time_spent_seconds' => 90,
        'completed_at' => now(),
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/mentor/evaluation-results')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
