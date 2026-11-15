<?php

use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\CompetencyAssessmentLink;
use App\Models\MentorshipRelationship;
use App\Models\PersonalityAssessment;
use App\Models\Plan;
use App\Models\SkillTag;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Evidence-link management routes (RF-014.4, .9). Same access matrix as the
 * progress panel: owner and assigned mentor with mentor_workspace may manage;
 * unassigned actors, cross-subject ids and IDOR deny without disclosing.
 * OCEAN competency links additionally require the OCEAN entitlement.
 */
uses(RefreshDatabase::class)->group('progress');

beforeEach(function (): void {
    Role::findOrCreate('mentor');
    Role::findOrCreate('mentee');
    Role::findOrCreate('admin');

    $this->mentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
        'client_type' => 'personal',
    ]));
    $this->mentor->assignRole('mentor');
    $this->mentee = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'client_type' => 'personal',
    ]));
    $this->mentee->assignRole('mentee');
    $this->relationship = MentorshipRelationship::create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $this->mentee->id,
        'mentor_id' => $this->mentor->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
});

function celPlan(string $code, array $entitlements): Plan
{
    return Plan::create([
        'code' => $code,
        'client_type' => 'personal',
        'name' => ['es' => $code],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_mentors' => 1,
        'entitlements' => $entitlements,
        'is_active' => true,
    ]);
}

function celSubscribe(User $user, Plan $plan): void
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

function celResult(User $user): AssessmentResult
{
    $assessment = Assessment::create([
        'slug' => 'cel-'.Str::random(8),
        'title' => ['es' => 'Evaluación'],
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

function celTag(): SkillTag
{
    return SkillTag::create([
        'name' => 'cel-skill-'.Str::random(8),
        'category' => 'test',
    ]);
}

function celPayload(AssessmentResult|PersonalityAssessment $source, string $competencyType, string $competencyKey): array
{
    return [
        'source_type' => $source instanceof AssessmentResult
            ? 'assessment_result'
            : 'personality_assessment',
        'source_id' => $source->id,
        'competency_type' => $competencyType,
        'competency_key' => $competencyKey,
    ];
}

it('lets the owner create a link', function (): void {
    $result = celResult($this->mentee);
    $tag = celTag();

    $response = $this->actingAs($this->mentee)
        ->postJson('/api/v1/progress/competencies/evidence-links', celPayload($result, 'skill_tag', $tag->id));

    $response->assertCreated()
        ->assertJsonPath('data.source_type', 'assessment_result')
        ->assertJsonPath('data.source_id', $result->id)
        ->assertJsonPath('data.competency_type', 'skill_tag')
        ->assertJsonPath('data.competency_key', $tag->id);

    expect(CompetencyAssessmentLink::query()
        ->where('user_id', $this->mentee->id)
        ->where('assessment_result_id', $result->id)
        ->where('skill_tag_id', $tag->id)
        ->exists())->toBeTrue();

    $activity = Activity::query()->where('event', 'competency.evidence_link_changed')->latest('id')->first();
    expect($activity->causer_id)->toBe($this->mentee->id)
        ->and($activity->properties['actor_id'])->toBe($this->mentee->id)
        ->and($activity->properties['subject_id'])->toBe($this->mentee->id);
});

it('lets the assigned mentor manage the mentee links', function (): void {
    celSubscribe($this->mentee, celPlan('cel-ws', ['mentor_workspace' => true]));
    $result = celResult($this->mentee);
    $tag = celTag();
    $other = celTag();

    $base = "/api/v1/mentor/users/{$this->mentee->id}/progress/competencies/evidence-links";

    $created = $this->actingAs($this->mentor)
        ->postJson($base, celPayload($result, 'skill_tag', $tag->id));
    $created->assertCreated();
    $linkId = $created->json('data.id');

    $this->actingAs($this->mentor)
        ->putJson("{$base}/{$linkId}", celPayload($result, 'skill_tag', $other->id))
        ->assertOk()
        ->assertJsonPath('data.competency_key', $other->id);

    $this->actingAs($this->mentor)
        ->deleteJson("{$base}/{$linkId}")
        ->assertNoContent();

    expect(CompetencyAssessmentLink::query()->find($linkId))->toBeNull();
});

it('updates and removes a link for the owner', function (): void {
    $result = celResult($this->mentee);
    $tag = celTag();
    $other = celTag();
    $link = CompetencyAssessmentLink::factory()
        ->forAssessmentResult($result)
        ->forSkillTag($tag)
        ->create(['user_id' => $this->mentee->id]);

    $this->actingAs($this->mentee)
        ->putJson("/api/v1/progress/competencies/evidence-links/{$link->id}", celPayload($result, 'skill_tag', $other->id))
        ->assertOk()
        ->assertJsonPath('data.competency_type', 'skill_tag')
        ->assertJsonPath('data.competency_key', $other->id);

    $this->actingAs($this->mentee)
        ->deleteJson("/api/v1/progress/competencies/evidence-links/{$link->id}")
        ->assertNoContent();
});

it('denies an unassigned mentor without disclosing the subject', function (): void {
    $stranger = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
        'client_type' => 'personal',
    ]));
    $stranger->assignRole('mentor');
    $result = celResult($this->mentee);

    $this->actingAs($stranger)
        ->postJson("/api/v1/mentor/users/{$this->mentee->id}/progress/competencies/evidence-links", celPayload($result, 'skill_tag', celTag()->id))
        ->assertNotFound();

    expect(CompetencyAssessmentLink::query()->count())->toBe(0);
});

it('denies the mentor when the mentee plan lacks the workspace', function (): void {
    celSubscribe($this->mentee, celPlan('cel-locked', ['mentor_workspace' => false]));
    $result = celResult($this->mentee);

    $this->actingAs($this->mentor)
        ->postJson("/api/v1/mentor/users/{$this->mentee->id}/progress/competencies/evidence-links", celPayload($result, 'skill_tag', celTag()->id))
        ->assertForbidden();

    expect(CompetencyAssessmentLink::query()->count())->toBe(0);
});

it('denies IDOR on foreign links and foreign sources without disclosure', function (): void {
    $other = User::factory()->create(['client_type' => 'personal']);
    $foreignLink = CompetencyAssessmentLink::factory()
        ->forAssessmentResult()
        ->forSkillTag(celTag())
        ->create(['user_id' => $other->id]);
    $foreignResult = celResult($other);
    $result = celResult($this->mentee);

    $this->actingAs($this->mentee)
        ->putJson("/api/v1/progress/competencies/evidence-links/{$foreignLink->id}", celPayload($result, 'skill_tag', celTag()->id))
        ->assertNotFound();
    expect($foreignLink->fresh()->skill_tag_id)->not->toBeNull();

    $this->actingAs($this->mentee)
        ->deleteJson("/api/v1/progress/competencies/evidence-links/{$foreignLink->id}")
        ->assertNotFound();
    expect(CompetencyAssessmentLink::query()->find($foreignLink->id))->not->toBeNull();

    $this->actingAs($this->mentee)
        ->postJson('/api/v1/progress/competencies/evidence-links', celPayload($foreignResult, 'skill_tag', celTag()->id))
        ->assertNotFound();
    expect(CompetencyAssessmentLink::query()->count())->toBe(1);
});

it('requires the OCEAN entitlement for ocean_factor competency links', function (): void {
    $personality = PersonalityAssessment::factory()->create([
        'user_id' => $this->mentee->id,
        'completed_at' => now(),
    ]);

    $this->actingAs($this->mentee)
        ->postJson('/api/v1/progress/competencies/evidence-links', celPayload($personality, 'ocean_factor', 'O'))
        ->assertForbidden();

    celSubscribe($this->mentee, celPlan('cel-ocean', ['ocean' => true]));

    $this->actingAs($this->mentee)
        ->postJson('/api/v1/progress/competencies/evidence-links', celPayload($personality, 'ocean_factor', 'O'))
        ->assertCreated();
});

it('validates the discriminated payload server-side', function (): void {
    $result = celResult($this->mentee);

    $this->actingAs($this->mentee)
        ->postJson('/api/v1/progress/competencies/evidence-links', [
            'source_type' => 'bogus',
            'source_id' => 'not-a-uuid',
            'competency_type' => 'skill_tag',
            'competency_key' => 'x',
        ])
        ->assertUnprocessable();

    $this->actingAs($this->mentee)
        ->postJson('/api/v1/progress/competencies/evidence-links', celPayload($result, 'ocean_factor', 'X'))
        ->assertUnprocessable();

    $this->actingAs($this->mentee)
        ->postJson('/api/v1/progress/competencies/evidence-links', celPayload($result, 'skill_tag', 'not-a-uuid'))
        ->assertUnprocessable();

    expect(CompetencyAssessmentLink::query()->count())->toBe(0);
});

it('rejects unauthenticated calls', function (): void {
    $this->postJson('/api/v1/progress/competencies/evidence-links', [])->assertUnauthorized();
    $this->putJson('/api/v1/progress/competencies/evidence-links/'.Str::uuid()->toString(), [])->assertUnauthorized();
    $this->deleteJson('/api/v1/progress/competencies/evidence-links/'.Str::uuid()->toString())->assertUnauthorized();
});
