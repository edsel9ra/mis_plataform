<?php

use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\CompetencyAssessmentLink;
use App\Models\PersonalityAssessment;
use App\Models\SkillTag;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Privacy & regression gate (RNF-014.1, .5): source content — answers, scores,
 * raw scores, questions, results payloads, skill level — must never leak into
 * API responses or audit-log properties, on any of the link surfaces.
 */
uses(RefreshDatabase::class)->group('progress');

beforeEach(function (): void {
    $this->user = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'client_type' => 'personal',
    ]));
});

/**
 * Substrings that would expose source content or a competency level anywhere
 * inside a serialized payload or a stored event.
 *
 * @return list<string>
 */
function prvForbidden(): array
{
    return ['answers', 'answer', 'score', 'raw_scores', 'questions', 'results', 'level'];
}

function prvResult(User $user): AssessmentResult
{
    $assessment = Assessment::create([
        'slug' => 'prv-'.Str::random(8),
        'title' => ['es' => 'Evaluación'],
        'questions' => [['id' => 1, 'options' => ['a', 'b']]],
        'is_active' => true,
    ]);

    return AssessmentResult::create([
        'user_id' => $user->id,
        'assessment_id' => $assessment->id,
        'answers' => [['question_id' => 1, 'option_index' => 0]],
        'score' => 92.5,
        'passed' => true,
        'completed_at' => now(),
    ]);
}

function prvTag(): SkillTag
{
    return SkillTag::create([
        'name' => 'prv-skill-'.Str::random(8),
        'category' => 'test',
    ]);
}

function prvPayload(AssessmentResult|PersonalityAssessment $source, SkillTag $tag): array
{
    return [
        'source_type' => $source instanceof AssessmentResult
            ? 'assessment_result'
            : 'personality_assessment',
        'source_id' => $source->id,
        'competency_type' => 'skill_tag',
        'competency_key' => $tag->id,
    ];
}

it('never leaks source content or level into link API responses', function (): void {
    $result = prvResult($this->user);
    $tag = prvTag();

    $created = $this->actingAs($this->user)
        ->postJson('/api/v1/progress/competencies/evidence-links', prvPayload($result, $tag))
        ->assertCreated();

    foreach (prvForbidden() as $needle) {
        expect(json_encode($created->json('data')))->not->toContain($needle);
    }

    $link = CompetencyAssessmentLink::query()->where('user_id', $this->user->id)->firstOrFail();
    $other = prvResult($this->user);

    $updated = $this->actingAs($this->user)
        ->putJson("/api/v1/progress/competencies/evidence-links/{$link->id}", prvPayload($other, $tag))
        ->assertOk();

    foreach (prvForbidden() as $needle) {
        expect(json_encode($updated->json('data')))->not->toContain($needle);
    }
});

it('records only relation metadata in the link-change audit event', function (): void {
    $result = prvResult($this->user);
    $tag = prvTag();

    $this->actingAs($this->user)
        ->postJson('/api/v1/progress/competencies/evidence-links', prvPayload($result, $tag))
        ->assertCreated();

    $link = CompetencyAssessmentLink::query()->where('user_id', $this->user->id)->firstOrFail();

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/progress/competencies/evidence-links/{$link->id}")
        ->assertNoContent();

    $activities = Activity::query()
        ->where('event', 'competency.evidence_link_changed')
        ->latest('id')
        ->limit(2)
        ->get();

    expect($activities)->toHaveCount(2);

    foreach ($activities as $activity) {
        $json = json_encode($activity->properties->toArray());
        foreach (prvForbidden() as $needle) {
            expect($json)->not->toContain($needle);
        }
    }
});

it('keeps the source-state event free of answers and scores', function (): void {
    $personality = PersonalityAssessment::factory()->create([
        'user_id' => $this->user->id,
        'completed_at' => now(),
    ]);
    CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($personality)
        ->forSkillTag(prvTag())
        ->create(['user_id' => $this->user->id]);

    $personality->update(['completed_at' => null]);

    $activity = Activity::query()
        ->where('event', 'competency.evidence_source_state_changed')
        ->latest('id')
        ->firstOrFail();

    $json = json_encode($activity->properties->toArray());
    foreach (prvForbidden() as $needle) {
        expect($json)->not->toContain($needle);
    }
});

it('keeps the link free of level even when the user skill has one', function (): void {
    $tag = prvTag();
    UserSkill::create([
        'user_id' => $this->user->id,
        'skill_tag_id' => $tag->id,
        'level' => 'advanced',
    ]);
    $result = prvResult($this->user);

    $this->actingAs($this->user)
        ->postJson('/api/v1/progress/competencies/evidence-links', prvPayload($result, $tag))
        ->assertCreated();

    // The link stores no level, and the skill level stays untouched.
    $link = CompetencyAssessmentLink::query()->where('user_id', $this->user->id)->firstOrFail();
    expect($link->getAttributes())->not->toHaveKey('level');
    expect(UserSkill::query()->where('user_id', $this->user->id)->value('level'))->toBe('advanced');
});
