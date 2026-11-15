<?php

use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\CompetencyAssessmentLink;
use App\Models\PersonalityAssessment;
use App\Models\Plan;
use App\Models\SkillTag;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Serialized evidence inside the panel competencies block (RF-014.1–.3,
 * .5–.6, .9). Only explicit links appear, each distinguishing its real
 * source (Assessment or test_version) and date; absent links, answers,
 * score and raw_scores are never serialized.
 */
uses(RefreshDatabase::class)->group('progress');

beforeEach(function (): void {
    $this->user = User::factory()->create(['client_type' => 'personal']);
});

function cepResult(User $user, array $attributes = []): AssessmentResult
{
    $assessment = Assessment::create([
        'slug' => 'cep-'.Str::random(8),
        'title' => ['es' => 'Evaluación de contexto'],
        'questions' => [['id' => 1, 'options' => ['a', 'b']]],
        'is_active' => true,
    ]);

    return AssessmentResult::create(array_merge([
        'user_id' => $user->id,
        'assessment_id' => $assessment->id,
        'answers' => [['question_id' => 1, 'option_index' => 0]],
        'score' => 92.5,
        'passed' => true,
        'completed_at' => now(),
    ], $attributes));
}

function cepSkill(User $user, ?SkillTag $tag = null): UserSkill
{
    $tag ??= SkillTag::create([
        'name' => 'cep-skill-'.Str::random(8),
        'category' => 'test',
    ]);

    return UserSkill::create([
        'user_id' => $user->id,
        'skill_tag_id' => $tag->id,
        'level' => 3,
    ]);
}

function cepOceanPlan(User $user): void
{
    $plan = Plan::create([
        'code' => 'cep-ocean-'.Str::random(6),
        'client_type' => 'personal',
        'name' => ['es' => 'ocean'],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_mentors' => 1,
        'entitlements' => ['ocean' => true],
        'is_active' => true,
    ]);
    Subscription::create([
        'subscriber_type' => 'user',
        'subscriber_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);
}

function cepEvidence(array $block, string $type, string $key): array
{
    if ($type === 'skill_tag') {
        foreach ($block['skills'] as $skill) {
            if ($skill['skill_tag_id'] === $key) {
                return $skill['evidence'];
            }
        }

        return [];
    }

    return $block['ocean']['evidence'][$key] ?? [];
}

it('serializes a linked catalog result with its assessment context and date', function (): void {
    $skill = cepSkill($this->user);
    $result = cepResult($this->user);
    $link = CompetencyAssessmentLink::factory()
        ->forAssessmentResult($result)
        ->forSkillTag(SkillTag::findOrFail($skill->skill_tag_id))
        ->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/progress/panel');
    $response->assertOk();

    $block = $response->json('data.competencies');
    $evidence = cepEvidence($block, 'skill_tag', $skill->skill_tag_id);

    expect($evidence)->toHaveCount(1)
        ->and($evidence[0]['link_id'])->toBe($link->id)
        ->and($evidence[0]['state'])->toBe('current')
        ->and($evidence[0]['source_type'])->toBe('assessment_result')
        ->and($evidence[0]['source_id'])->toBe($result->id)
        ->and($evidence[0]['instrument']['slug'])->toBe($result->assessment->slug)
        ->and($evidence[0]['instrument']['title'])->toBe($result->assessment->title)
        ->and($evidence[0]['completed_at'])->not->toBeNull();
});

it('serializes a linked personality result with test_version per OCEAN factor', function (): void {
    cepOceanPlan($this->user);
    $personality = PersonalityAssessment::factory()->create([
        'user_id' => $this->user->id,
        'completed_at' => now(),
    ]);
    CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($personality)
        ->forOceanFactor('C')
        ->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/progress/panel');
    $block = $response->json('data.competencies');
    $evidence = cepEvidence($block, 'ocean_factor', 'C');

    expect($evidence)->toHaveCount(1)
        ->and($evidence[0]['source_type'])->toBe('personality_assessment')
        ->and($evidence[0]['source_id'])->toBe($personality->id)
        ->and($evidence[0]['test_version'])->toBe($personality->test_version)
        ->and($evidence[0]['state'])->toBe('current');

    // The block exposes the subject's own assessment id so an authorized
    // viewer can link the personality source (no answers or scores).
    expect($block['ocean']['assessment_id'])->toBe($personality->id);

    // The other factors must not inherit the link.
    expect(cepEvidence($block, 'ocean_factor', 'O'))->toBe([]);
});

it('keeps an incomplete linked source as historical, never current', function (): void {
    cepOceanPlan($this->user);
    $personality = PersonalityAssessment::factory()->create([
        'user_id' => $this->user->id,
        'completed_at' => null,
    ]);
    CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($personality)
        ->forOceanFactor('A')
        ->create(['user_id' => $this->user->id]);

    $block = $this->actingAs($this->user)->getJson('/api/v1/progress/panel')->json('data.competencies');
    $evidence = cepEvidence($block, 'ocean_factor', 'A');

    expect($evidence)->toHaveCount(1)
        ->and($evidence[0]['state'])->toBe('incomplete')
        ->and($evidence[0]['completed_at'])->toBeNull();
});

it('shows no evidence without an explicit link and never infers one', function (): void {
    $skill = cepSkill($this->user);
    cepResult($this->user); // completed result exists but is NOT linked

    $block = $this->actingAs($this->user)->getJson('/api/v1/progress/panel')->json('data.competencies');

    expect(cepEvidence($block, 'skill_tag', $skill->skill_tag_id))->toBe([]);
});

it('never serializes answers, score or raw_scores in evidence entries', function (): void {
    $skill = cepSkill($this->user);
    $result = cepResult($this->user);
    CompetencyAssessmentLink::factory()
        ->forAssessmentResult($result)
        ->forSkillTag(SkillTag::findOrFail($skill->skill_tag_id))
        ->create(['user_id' => $this->user->id]);

    $block = $this->actingAs($this->user)->getJson('/api/v1/progress/panel')->json('data.competencies');
    $evidence = cepEvidence($block, 'skill_tag', $skill->skill_tag_id);
    $json = json_encode($evidence);

    expect($json)->not->toContain('answers')
        ->not->toContain('score')
        ->not->toContain('raw_scores')
        ->not->toContain('92.5')
        ->not->toContain('level');
});

it('blocks OCEAN evidence without entitlement without exposing source fields', function (): void {
    $personality = PersonalityAssessment::factory()->create([
        'user_id' => $this->user->id,
        'completed_at' => now(),
    ]);
    CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($personality)
        ->forOceanFactor('N')
        ->create(['user_id' => $this->user->id]);

    $block = $this->actingAs($this->user)->getJson('/api/v1/progress/panel')->json('data.competencies');
    $evidence = cepEvidence($block, 'ocean_factor', 'N');

    expect($evidence)->toHaveCount(1)
        ->and($evidence[0]['state'])->toBe('blocked')
        ->and($evidence[0])->not->toHaveKey('source_id')
        ->and($evidence[0])->not->toHaveKey('test_version');
});
