<?php

use App\Models\PersonalityAssessment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

uses()->group('personality');

beforeEach(function () {
    $this->user = User::factory()->create(['birth_date' => now()->subYears(30)->toDateString()]);
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

it('rejects duplicate question ids', function () {
    Http::fake();

    $answers = array_map(
        fn (int $id) => ['id_question' => $id, 'id_select' => 3],
        range(1, 119),
    );
    $answers[] = ['id_question' => 1, 'id_select' => 3];

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/personality/submit-answers', [
            'answers' => $answers,
            'test_version' => 'ipip-neo-120',
        ]);

    $response->assertUnprocessable();
    Http::assertNothingSent();
});

it('rejects question ids outside the selected test range', function () {
    Http::fake();

    $answers = array_map(
        fn (int $id) => ['id_question' => $id, 'id_select' => 3],
        range(1, 119),
    );
    $answers[] = ['id_question' => 121, 'id_select' => 3];

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/personality/submit-answers', [
            'answers' => $answers,
            'test_version' => 'ipip-neo-120',
        ]);

    $response->assertUnprocessable();
    Http::assertNothingSent();
});

it('submits exactly 120 valid answers to the matching service', function () {
    Http::fake([
        '*' => Http::response([
            'factors' => ['O' => 50, 'C' => 51, 'E' => 52, 'A' => 53, 'N' => 54],
            'facets' => [],
            'raw_scores' => ['fixture' => true],
            'test_version' => 'ipip-neo-120',
        ]),
    ]);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/personality/submit-answers', [
            'answers' => array_map(
                fn (int $id) => ['id_question' => $id, 'id_select' => 3],
                range(1, 120),
            ),
            'test_version' => 'ipip-neo-120',
        ]);

    $response->assertCreated()
        ->assertJsonPath('results.factors.O', 50);

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return str_ends_with($request->url(), '/api/v1/personality/score')
            && $payload['test_version'] === 'ipip-neo-120'
            && count($payload['answers']) === 120
            && $payload['answers'][1] === 3
            && $payload['answers'][120] === 3;
    });
});

it('rejects calculate requests that do not match the selected test length', function () {
    Http::fake();

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/personality/calculate', [
            'answers' => array_fill(1, 119, 3),
            'test_version' => 'ipip-neo-120',
            'age' => 30,
            'sex' => 'N',
        ]);

    $response->assertUnprocessable();
    Http::assertNothingSent();
});

it('shows personality report', function () {
    PersonalityAssessment::factory()->create(['user_id' => $this->user->id]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/personality/report');

    $response->assertOk()
        ->assertJsonStructure(['test_version', 'factors', 'interpretation']);
});

it('marks every ocean factor below 51 as needing improvement', function () {
    PersonalityAssessment::factory()->create([
        'user_id' => $this->user->id,
        'results' => [
            'factors' => [
                'O' => 50,
                'C' => 50.99,
                'E' => 51,
                'A' => 80,
                'N' => 10,
            ],
        ],
    ]);

    $this->withToken($this->token)
        ->getJson('/api/v1/personality/report')
        ->assertOk()
        ->assertJsonPath('interpretation.O.needs_improvement', true)
        ->assertJsonPath('interpretation.C.needs_improvement', true)
        ->assertJsonPath('interpretation.E.needs_improvement', false)
        ->assertJsonPath('interpretation.A.needs_improvement', false)
        ->assertJsonPath('interpretation.N.needs_improvement', true)
        ->assertJsonPath('interpretation.O.level', 'Medio');
});

it('does not mark factors at or above 51 as needing improvement', function () {
    PersonalityAssessment::factory()->create([
        'user_id' => $this->user->id,
        'results' => [
            'factors' => [
                'O' => 51,
                'C' => 65,
                'E' => 70,
                'A' => 51.01,
                'N' => 100,
            ],
        ],
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/personality/report');

    $response->assertOk();

    foreach (['O', 'C', 'E', 'A', 'N'] as $factor) {
        $response->assertJsonPath("interpretation.{$factor}.needs_improvement", false);
    }
});
