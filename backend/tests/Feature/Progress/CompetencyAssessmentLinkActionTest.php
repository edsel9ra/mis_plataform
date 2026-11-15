<?php

use App\Actions\RemoveCompetencyAssessmentLinkAction;
use App\Actions\SetCompetencyAssessmentLinkAction;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\CompetencyAssessmentLink;
use App\Models\PersonalityAssessment;
use App\Models\SkillTag;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class)->group('progress');

function calActAssessmentResult(User $user): AssessmentResult
{
    $assessment = Assessment::create([
        'slug' => 'cal-act-'.Str::random(8),
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

function calActSkillTag(): SkillTag
{
    return SkillTag::create([
        'name' => 'cal-skill-'.Str::random(8),
        'category' => 'test',
    ]);
}

function calActEvents(): \Illuminate\Database\Eloquent\Collection
{
    return Activity::query()
        ->where('event', 'competency.evidence_link_changed')
        ->latest('id')
        ->get();
}

beforeEach(function (): void {
    $this->set = app(SetCompetencyAssessmentLinkAction::class);
    $this->remove = app(RemoveCompetencyAssessmentLinkAction::class);
    $this->user = User::factory()->create();
});

it('creates a link and audits the absent previous relation', function (): void {
    $result = calActAssessmentResult($this->user);
    $tag = calActSkillTag();
    $now = Carbon::parse('2026-11-14T12:00:00Z');

    $link = $this->set->execute(
        $this->user, $this->user, null,
        'assessment_result', $result->id,
        'skill_tag', $tag->id,
        $now,
    );

    expect($link->exists)->toBeTrue()
        ->and($link->user_id)->toBe($this->user->id)
        ->and($link->assessment_result_id)->toBe($result->id)
        ->and($link->skill_tag_id)->toBe($tag->id);

    $props = calActEvents()->first()->properties->toArray();
    expect($props['actor_id'])->toBe($this->user->id)
        ->and($props['subject_id'])->toBe($this->user->id)
        ->and($props['before'])->toBeNull()
        ->and($props['after'])->toMatchArray([
            'source_type' => 'assessment_result',
            'source_id' => $result->id,
            'competency_type' => 'skill_tag',
            'competency_key' => $tag->id,
        ])
        ->and($props['occurred_at'])->toBe($now->toIso8601String());
});

it('changes a link preserving the before and after relation', function (): void {
    $result = calActAssessmentResult($this->user);
    $tagA = calActSkillTag();
    $tagB = calActSkillTag();

    $link = CompetencyAssessmentLink::factory()
        ->forAssessmentResult($result)->forSkillTag($tagA)
        ->create(['user_id' => $this->user->id]);

    $updated = $this->set->execute(
        $this->user, $this->user, $link,
        'assessment_result', $result->id,
        'skill_tag', $tagB->id,
        now(),
    );

    expect($updated->id)->toBe($link->id)
        ->and($updated->skill_tag_id)->toBe($tagB->id);
    expect(CompetencyAssessmentLink::count())->toBe(1);

    $props = calActEvents()->first()->properties->toArray();
    expect($props['before']['competency_key'])->toBe($tagA->id)
        ->and($props['after']['competency_key'])->toBe($tagB->id);
});

it('removes a link and audits the absent resulting relation', function (): void {
    $result = calActAssessmentResult($this->user);
    $tag = calActSkillTag();
    $link = CompetencyAssessmentLink::factory()
        ->forAssessmentResult($result)->forSkillTag($tag)
        ->create(['user_id' => $this->user->id]);

    $this->remove->execute($this->user, $this->user, $link, now());

    expect(CompetencyAssessmentLink::find($link->id))->toBeNull();

    $props = calActEvents()->first()->properties->toArray();
    expect($props['before'])->toMatchArray(['competency_key' => $tag->id])
        ->and($props['after'])->toBeNull();
});

it('links one result to several competencies without exclusivity', function (): void {
    $result = calActAssessmentResult($this->user);
    $tagA = calActSkillTag();
    $tagB = calActSkillTag();

    $this->set->execute($this->user, $this->user, null, 'assessment_result', $result->id, 'skill_tag', $tagA->id, now());
    $this->set->execute($this->user, $this->user, null, 'assessment_result', $result->id, 'ocean_factor', 'C', now());
    $otherResult = calActAssessmentResult($this->user);
    $this->set->execute($this->user, $this->user, null, 'assessment_result', $otherResult->id, 'skill_tag', $tagA->id, now());

    expect(CompetencyAssessmentLink::count())->toBe(3);
});

it('rejects a duplicated (user, source, competency) link without writing', function (): void {
    $result = calActAssessmentResult($this->user);
    $tag = calActSkillTag();
    CompetencyAssessmentLink::factory()
        ->forAssessmentResult($result)->forSkillTag($tag)
        ->create(['user_id' => $this->user->id]);

    expect(fn () => $this->set->execute(
        $this->user, $this->user, null,
        'assessment_result', $result->id,
        'skill_tag', $tag->id,
        now(),
    ))->toThrow(ValidationException::class);

    expect(CompetencyAssessmentLink::count())->toBe(1);
});

it('does not disclose or modify sources owned by another subject', function (): void {
    $other = User::factory()->create();
    $foreignResult = calActAssessmentResult($other);
    $tag = calActSkillTag();

    expect(fn () => $this->set->execute(
        $this->user, $this->user, null,
        'assessment_result', $foreignResult->id,
        'skill_tag', $tag->id,
        now(),
    ))->toThrow(ModelNotFoundException::class);

    // A link owned by another subject is equally invisible to change/remove.
    $foreignLink = CompetencyAssessmentLink::factory()
        ->forAssessmentResult($foreignResult)->forSkillTag($tag)
        ->create(['user_id' => $other->id]);

    expect(fn () => $this->set->execute(
        $this->user, $this->user, $foreignLink,
        'assessment_result', $foreignResult->id,
        'ocean_factor', 'O',
        now(),
    ))->toThrow(ModelNotFoundException::class);
    expect(fn () => $this->remove->execute($this->user, $this->user, $foreignLink, now()))
        ->toThrow(ModelNotFoundException::class);

    expect($foreignLink->fresh()->ocean_factor)->toBeNull();
});

it('rejects an uncompleted source and an invalid competency', function (): void {
    $tag = calActSkillTag();

    // assessment_results.completed_at is NOT NULL — a catalog row is always
    // completed by schema; only personality results can exist uncompleted.
    $personality = PersonalityAssessment::factory()->create([
        'user_id' => $this->user->id,
        'completed_at' => null,
    ]);

    expect(fn () => $this->set->execute($this->user, $this->user, null, 'personality_assessment', $personality->id, 'skill_tag', $tag->id, now()))
        ->toThrow(ValidationException::class);
    expect(fn () => $this->set->execute($this->user, $this->user, null, 'assessment_result', calActAssessmentResult($this->user)->id, 'ocean_factor', 'X', now()))
        ->toThrow(ValidationException::class);
    expect(fn () => $this->set->execute($this->user, $this->user, null, 'assessment_result', calActAssessmentResult($this->user)->id, 'skill_tag', (string) Str::uuid(), now()))
        ->toThrow(ModelNotFoundException::class);

    expect(CompetencyAssessmentLink::count())->toBe(0);
});

it('never mutates UserSkill.level and invalidates the subject panel cache', function (): void {
    $result = calActAssessmentResult($this->user);
    $tag = calActSkillTag();
    $skill = UserSkill::create([
        'user_id' => $this->user->id,
        'skill_tag_id' => $tag->id,
        'level' => 'beginner',
    ]);

    $bucket = \Carbon\CarbonImmutable::now()->format('Y-m');
    Cache::put('progress:panel:'.$this->user->id.':'.$bucket, ['cached' => true], 600);

    $link = $this->set->execute(
        $this->user, $this->user, null,
        'assessment_result', $result->id,
        'skill_tag', $tag->id,
        now(),
    );

    expect($skill->fresh()->level)->toBe('beginner');
    expect(Cache::has('progress:panel:'.$this->user->id.':'.$bucket))->toBeFalse();

    Cache::put('progress:panel:'.$this->user->id.':'.$bucket, ['cached' => true], 600);
    $this->remove->execute($this->user, $this->user, $link, now());
    expect(Cache::has('progress:panel:'.$this->user->id.':'.$bucket))->toBeFalse();
    expect($skill->fresh()->level)->toBe('beginner');
});

it('rolls back the write when the change fails mid-transaction', function (): void {
    $result = calActAssessmentResult($this->user);
    $tagA = calActSkillTag();
    $tagB = calActSkillTag();
    $link = CompetencyAssessmentLink::factory()
        ->forAssessmentResult($result)->forSkillTag($tagA)
        ->create(['user_id' => $this->user->id]);

    // Changing the link to a duplicate (another user row would violate the
    // partial unique index) must leave the original relation untouched.
    $other = CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment()->forSkillTag($tagB)
        ->create(['user_id' => $this->user->id]);

    expect(fn () => $this->set->execute(
        $this->user, $this->user, $link,
        'personality_assessment', $other->personality_assessment_id,
        'skill_tag', $tagB->id,
        now(),
    ))->toThrow(ValidationException::class);

    expect($link->fresh()->skill_tag_id)->toBe($tagA->id);
    expect(calActEvents())->toHaveCount(0);
});
