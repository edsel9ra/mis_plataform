<?php

use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\CompetencyAssessmentLink;
use App\Models\PersonalityAssessment;
use App\Models\SkillTag;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/**
 * Whitelisted events for real source transitions while a link is active
 * (RF-014.4, .6–.7; consumed by Spec 015). Only a genuinely persisted
 * completed_at transition on a linked source produces an event — no
 * synthetic transitions, no level mutation. Source deletions retire the
 * links through the audited removal path before the restrictive FK is hit.
 */
uses(RefreshDatabase::class)->group('progress');

beforeEach(function (): void {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
    $this->user = User::factory()->create(['client_type' => 'personal']);
    $this->admin = User::factory()->superAdmin()->create();
    $this->admin->assignRole('super_admin');
});

function cesePersonality(User $user, array $attributes = []): PersonalityAssessment
{
    return PersonalityAssessment::factory()->create(array_merge([
        'user_id' => $user->id,
        'completed_at' => now(),
    ], $attributes));
}

function ceseLink(PersonalityAssessment $source, ?string $factor = 'O'): CompetencyAssessmentLink
{
    return CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($source)
        ->forOceanFactor($factor)
        ->create(['user_id' => $source->user_id]);
}

function ceseEvents(string $event): \Illuminate\Database\Eloquent\Collection
{
    return Activity::query()->where('event', $event)->latest('id')->get();
}

it('emits a whitelisted event when a linked source becomes completed', function (): void {
    $source = cesePersonality($this->user, ['completed_at' => null]);
    $link = ceseLink($source);
    $skill = UserSkill::create([
        'user_id' => $this->user->id,
        'skill_tag_id' => SkillTag::create(['name' => 'cese-'.Str::random(6), 'category' => 't'])->id,
        'level' => 2,
    ]);

    $source->update(['completed_at' => now()]);

    $events = ceseEvents('competency.evidence_source_state_changed');
    expect($events)->toHaveCount(1);
    $props = $events->first()->properties;
    expect($props['source_id'])->toBe($source->id)
        ->and($props['subject_id'])->toBe($this->user->id)
        ->and($props['link_ids'])->toContain($link->id)
        ->and($props['before']['state'])->toBe('incomplete')
        ->and($props['after']['state'])->toBe('current')
        ->and($props)->toHaveKey('occurred_at');

    // The link and the level are both untouched.
    expect(CompetencyAssessmentLink::query()->find($link->id))->not->toBeNull()
        ->and($skill->fresh()->level)->toBe('2');
});

it('emits the transition to incomplete and keeps the historical link', function (): void {
    $source = cesePersonality($this->user);
    ceseLink($source);

    $source->update(['completed_at' => null]);

    $events = ceseEvents('competency.evidence_source_state_changed');
    expect($events)->toHaveCount(1);
    $props = $events->first()->properties;
    expect($props['before']['state'])->toBe('current')
        ->and($props['after']['state'])->toBe('incomplete');
    expect(CompetencyAssessmentLink::query()->count())->toBe(1);
});

it('does not emit when the update does not change completed_at', function (): void {
    $source = cesePersonality($this->user);
    ceseLink($source);

    $source->update(['results' => ['factors' => ['O' => 60]]]);

    expect(ceseEvents('competency.evidence_source_state_changed'))->toHaveCount(0);
});

it('does not emit for an unlinked source transition', function (): void {
    $source = cesePersonality($this->user, ['completed_at' => null]);

    $source->update(['completed_at' => now()]);

    expect(ceseEvents('competency.evidence_source_state_changed'))->toHaveCount(0);
});

it('retires linked evidence with audit when the user retakes the test', function (): void {
    // A completed source whose factors are empty reaches the retake delete path.
    $source = cesePersonality($this->user, ['results' => ['factors' => []]]);
    $link = ceseLink($source);

    Http::fake([
        '*' => Http::response([
            'factors' => ['O' => 50, 'C' => 51, 'E' => 52, 'A' => 53, 'N' => 54],
            'raw_scores' => [],
            'test_version' => 'ipip-neo-120',
        ]),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/personality/submit-answers', [
            'answers' => array_map(
                fn (int $id) => ['id_question' => $id, 'id_select' => 3],
                range(1, 120),
            ),
            'age' => 30,
            'test_version' => 'ipip-neo-120',
        ]);

    $response->assertCreated();
    expect(PersonalityAssessment::query()->find($source->id))->toBeNull()
        ->and(CompetencyAssessmentLink::query()->find($link->id))->toBeNull();

    // Each retirement is an audited event with an explicitly absent result.
    $retirements = ceseEvents('competency.evidence_link_changed');
    expect($retirements)->toHaveCount(1);
    $props = $retirements->first()->properties;
    expect($props['after'])->toBeNull()
        ->and($props['before']['source_id'])->toBe($source->id)
        ->and($props['actor_id'])->toBe($this->user->id);
});

it('retires links with audit when admin destroys a linked personality assessment', function (): void {
    $source = cesePersonality($this->user);
    $link = ceseLink($source);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/assessments/{$source->id}")
        ->assertOk();

    expect(PersonalityAssessment::query()->find($source->id))->toBeNull()
        ->and(CompetencyAssessmentLink::query()->find($link->id))->toBeNull();

    $props = ceseEvents('competency.evidence_link_changed')->first()->properties;
    expect($props['after'])->toBeNull()
        ->and($props['actor_id'])->toBe($this->admin->id);
});

it('retires links when a catalog assessment delete cascades to linked results', function (): void {
    $assessment = Assessment::create([
        'slug' => 'cese-'.Str::random(8),
        'title' => ['es' => 'Catálogo'],
        'questions' => [['id' => 1, 'options' => ['a', 'b']]],
        'is_active' => true,
    ]);
    $result = AssessmentResult::create([
        'user_id' => $this->user->id,
        'assessment_id' => $assessment->id,
        'answers' => [['question_id' => 1, 'option_index' => 0]],
        'score' => 80,
        'passed' => true,
        'completed_at' => now(),
    ]);
    $link = CompetencyAssessmentLink::factory()
        ->forAssessmentResult($result)
        ->forSkillTag(SkillTag::create(['name' => 'cese-'.Str::random(6), 'category' => 't']))
        ->create(['user_id' => $this->user->id]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/evaluation-catalog/{$assessment->id}")
        ->assertOk();

    expect(Assessment::query()->find($assessment->id))->toBeNull()
        ->and(AssessmentResult::query()->find($result->id))->toBeNull()
        ->and(CompetencyAssessmentLink::query()->find($link->id))->toBeNull();

    $props = ceseEvents('competency.evidence_link_changed')->first()->properties;
    expect($props['after'])->toBeNull()
        ->and($props['before']['source_id'])->toBe($result->id)
        ->and($props['actor_id'])->toBe($this->admin->id);
});
