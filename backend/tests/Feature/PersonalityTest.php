<?php

use App\Models\PersonalityAssessment;
use App\Models\User;

uses()->group('personality');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('starts a personality test', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/personality/start-test');

    $response->assertOk()
        ->assertJsonStructure(['test_version', 'total_questions', 'questions']);
});

it('returns existing assessment on retry', function () {
    PersonalityAssessment::factory()->create(['user_id' => $this->user->id]);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/personality/start-test');

    $response->assertOk()
        ->assertJsonPath('assessment.test_version', 'ipip-neo-120');
});

it('rejects duplicate submission', function () {
    PersonalityAssessment::factory()->create(['user_id' => $this->user->id]);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/personality/submit-answers', [
            'answers' => array_map(
                fn (int $id) => ['id_question' => $id, 'id_select' => 3],
                range(1, 120),
            ),
            'test_version' => 'ipip-neo-120',
        ]);

    $response->assertStatus(409);
});

it('requires minimum 120 answers', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/personality/submit-answers', [
            'answers' => [['id_question' => 1, 'id_select' => 3]],
            'test_version' => 'ipip-neo-120',
        ]);

    $response->assertUnprocessable();
});

it('shows personality report', function () {
    PersonalityAssessment::factory()->create(['user_id' => $this->user->id]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/personality/report');

    $response->assertOk()
        ->assertJsonStructure(['test_version', 'factors', 'interpretation']);
});
