<?php

use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\CompetencyAssessmentLink;
use App\Models\PersonalityAssessment;
use App\Models\SkillTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class)->group('progress');

function calModelAssessmentResult(User $user): AssessmentResult
{
    $assessment = Assessment::create([
        'slug' => 'cal-model-'.Str::random(8),
        'title' => ['es' => 'Evaluación de prueba'],
        'questions' => [['id' => 1, 'options' => ['a', 'b']]],
        'is_active' => true,
    ]);

    return AssessmentResult::create([
        'user_id' => $user->id,
        'assessment_id' => $assessment->id,
        'answers' => [['question_id' => 1, 'option_index' => 0]],
        'score' => 80,
        'passed' => true,
        'completed_at' => now(),
    ]);
}

function calModelSkillTag(): SkillTag
{
    return SkillTag::create([
        'name' => 'cal-skill-'.Str::random(8),
        'category' => 'test',
    ]);
}

it('resolves a catalog-result link to a skill with its Assessment preserved', function (): void {
    $user = User::factory()->create();
    $result = calModelAssessmentResult($user);
    $tag = calModelSkillTag();

    $link = CompetencyAssessmentLink::create([
        'user_id' => $user->id,
        'assessment_result_id' => $result->id,
        'skill_tag_id' => $tag->id,
    ]);

    expect($link->sourceType())->toBe('assessment_result');
    expect($link->competencyType())->toBe('skill_tag');
    expect($link->competencyKey())->toBe($tag->id);
    expect($link->source()->is($result))->toBeTrue();
    expect($link->assessmentResult->is($result))->toBeTrue();
    expect($link->skillTag->is($tag))->toBeTrue();
    expect($link->personality_assessment_id)->toBeNull();
    expect($link->ocean_factor)->toBeNull();

    // The origin keeps its Assessment (RF-014.2); the level stays in user_skills.
    expect($link->assessmentResult->assessment)->not->toBeNull();
    expect($result->competencyAssessmentLinks->pluck('id'))->toContain($link->id);
    expect($tag->competencyAssessmentLinks->pluck('id'))->toContain($link->id);
    expect($user->competencyAssessmentLinks->pluck('id'))->toContain($link->id);
});

it('resolves a personality link to an OCEAN factor without an Assessment', function (): void {
    $user = User::factory()->create();
    $personality = PersonalityAssessment::factory()->create([
        'user_id' => $user->id,
        'test_version' => 'ipip-neo-300',
    ]);

    $link = CompetencyAssessmentLink::create([
        'user_id' => $user->id,
        'personality_assessment_id' => $personality->id,
        'ocean_factor' => 'C',
    ]);

    expect($link->sourceType())->toBe('personality_assessment');
    expect($link->competencyType())->toBe('ocean_factor');
    expect($link->competencyKey())->toBe('C');
    expect($link->source()->is($personality))->toBeTrue();
    expect($link->skill_tag_id)->toBeNull();
    expect($link->assessment_result_id)->toBeNull();

    // RF-014.2: an OCEAN result never needs nor creates an Assessment;
    // its origin marker is test_version.
    expect($personality->fresh()->test_version)->toBe('ipip-neo-300');
    expect(Assessment::count())->toBe(0);
    expect($personality->competencyAssessmentLinks->pluck('id'))->toContain($link->id);
});

it('links one result to several competencies and one competency to several results', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $result = calModelAssessmentResult($user);
    $tagA = calModelSkillTag();
    $tagB = calModelSkillTag();

    // Same result → two different skills (many-to-many, no exclusivity).
    CompetencyAssessmentLink::create([
        'user_id' => $user->id,
        'assessment_result_id' => $result->id,
        'skill_tag_id' => $tagA->id,
    ]);
    CompetencyAssessmentLink::create([
        'user_id' => $user->id,
        'assessment_result_id' => $result->id,
        'skill_tag_id' => $tagB->id,
    ]);

    // Same skill ← a different result from another subject.
    $otherResult = calModelAssessmentResult($other);
    CompetencyAssessmentLink::create([
        'user_id' => $other->id,
        'assessment_result_id' => $otherResult->id,
        'skill_tag_id' => $tagA->id,
    ]);

    expect($result->competencyAssessmentLinks()->count())->toBe(2);
    expect($tagA->competencyAssessmentLinks()->count())->toBe(2);
});

it('scopes links by owning user without touching other subjects', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    CompetencyAssessmentLink::create([
        'user_id' => $user->id,
        'personality_assessment_id' => PersonalityAssessment::factory()->create(['user_id' => $user->id])->id,
        'ocean_factor' => 'O',
    ]);
    CompetencyAssessmentLink::create([
        'user_id' => $other->id,
        'personality_assessment_id' => PersonalityAssessment::factory()->create(['user_id' => $other->id])->id,
        'ocean_factor' => 'E',
    ]);

    expect($user->competencyAssessmentLinks()->count())->toBe(1);
    expect($user->competencyAssessmentLinks()->sole()->ocean_factor)->toBe('O');
});

it('stores no competency level on the link itself', function (): void {
    expect(Schema::hasColumn('competency_assessment_links', 'level'))->toBeFalse();

    $link = new CompetencyAssessmentLink;
    expect($link->getFillable())->not->toContain('level');
});
