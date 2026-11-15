<?php

use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\CompetencyAssessmentLink;
use App\Models\EvidenceDeletionActorLink;
use App\Models\EvidenceDeletionEvent;
use App\Models\PersonalityAssessment;
use App\Models\SkillTag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/**
 * T014.11 — minimal deletion event (política-retencion §5, plan §9) recorded
 * whenever a linked evidence source is deleted through any of the three
 * funnels of RetireEvidenceLinksForSourceAction. The event carries only
 * discriminators + a per-event pseudonym; the real actor lives solely in
 * evidence_deletion_actor_links. The operational audit event coexists.
 */
uses(RefreshDatabase::class)->group('progress');

beforeEach(function (): void {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
    $this->user = User::factory()->create(['client_type' => 'personal']);
    $this->admin = User::factory()->superAdmin()->create();
    $this->admin->assignRole('super_admin');
    $this->travelTo(CarbonImmutable::parse('2026-11-15T10:00:00Z'));
});

function edrPersonality(User $user, array $attributes = []): PersonalityAssessment
{
    return PersonalityAssessment::factory()->create(array_merge([
        'user_id' => $user->id,
        'completed_at' => now(),
    ], $attributes));
}

function edrOceanLink(PersonalityAssessment $source, string $factor): CompetencyAssessmentLink
{
    return CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($source)
        ->forOceanFactor($factor)
        ->create(['user_id' => $source->user_id]);
}

function edrCatalogResult(User $user, SkillTag $tag): array
{
    $assessment = Assessment::create([
        'slug' => 'edr-'.Str::random(8),
        'title' => ['es' => 'Catálogo'],
        'questions' => [['id' => 1, 'options' => ['a', 'b']]],
        'is_active' => true,
    ]);
    $result = AssessmentResult::create([
        'user_id' => $user->id,
        'assessment_id' => $assessment->id,
        'answers' => [['question_id' => 1, 'option_index' => 0]],
        'score' => 80,
        'passed' => true,
        'completed_at' => now(),
    ]);
    $link = CompetencyAssessmentLink::factory()
        ->forAssessmentResult($result)
        ->forSkillTag($tag)
        ->create(['user_id' => $user->id]);

    return [$assessment, $result, $link];
}

function edrRetake(User $user): \Illuminate\Testing\TestResponse
{
    Http::fake([
        '*' => Http::response([
            'factors' => ['O' => 50, 'C' => 51, 'E' => 52, 'A' => 53, 'N' => 54],
            'raw_scores' => [],
            'test_version' => 'ipip-neo-120',
        ]),
    ]);

    return test()->actingAs($user)->postJson('/api/v1/personality/submit-answers', [
        'answers' => array_map(
            fn (int $id) => ['id_question' => $id, 'id_select' => 3],
            range(1, 120),
        ),
        'age' => 30,
        'test_version' => 'ipip-neo-120',
    ]);
}

it('records one minimal event per link on the retake delete path', function (): void {
    // Empty factors → the retake reaches the delete branch (not the 409 path).
    $source = edrPersonality($this->user, ['results' => ['factors' => []]]);
    $linkO = edrOceanLink($source, 'O');
    $linkC = edrOceanLink($source, 'C');

    edrRetake($this->user)->assertCreated();

    $events = EvidenceDeletionEvent::query()->orderBy('competency_key')->get();
    expect($events)->toHaveCount(2);

    // Persisted timestamps hold the app.timezone wall clock (project
    // convention); compare the wall clock, not the offset label.
    $expected = CarbonImmutable::parse('2026-11-15T10:00:00Z')->setTimezone(config('app.timezone'));
    $expectedExpiry = $expected->addMonthsNoOverflow(12);
    foreach ([['C', $events[0]], ['O', $events[1]]] as [$factor, $event]) {
        expect($event->subject_id)->toBe($this->user->id)
            ->and($event->source_type)->toBe('personality_assessment')
            ->and($event->competency_type)->toBe('ocean_factor')
            ->and($event->competency_key)->toBe($factor)
            ->and($event->change_type)->toBe('source_deleted')
            ->and($event->occurred_at->format('Y-m-d H:i:s'))->toBe($expected->format('Y-m-d H:i:s'))
            ->and($event->expires_at->format('Y-m-d H:i:s'))->toBe($expectedExpiry->format('Y-m-d H:i:s'));

        $actorLink = EvidenceDeletionActorLink::query()->where('event_id', $event->id)->first();
        expect($actorLink)->not->toBeNull()
            ->and($actorLink->actor_id)->toBe($this->user->id);
    }

    // Per-event pseudonyms differ from each other and from the real actor.
    expect($events[0]->actor_pseudonym)->not->toBe($events[1]->actor_pseudonym)
        ->and($events[0]->actor_pseudonym)->not->toBe($this->user->id);
});

it('records the event with the admin as actor on DELETE /admin/assessments', function (): void {
    $source = edrPersonality($this->user);
    $link = edrOceanLink($source, 'O');

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/assessments/{$source->id}")
        ->assertOk();

    $event = EvidenceDeletionEvent::query()->sole();
    expect($event->source_type)->toBe('personality_assessment')
        ->and($event->competency_type)->toBe('ocean_factor')
        ->and($event->competency_key)->toBe('O')
        ->and($event->subject_id)->toBe($this->user->id);

    $actorLink = EvidenceDeletionActorLink::query()->where('event_id', $event->id)->sole();
    expect($actorLink->actor_id)->toBe($this->admin->id);
});

it('records a catalog-result event on DELETE /admin/evaluation-catalog', function (): void {
    $tag = SkillTag::create(['name' => 'edr-'.Str::random(6), 'category' => 't']);
    [$assessment, $result, $link] = edrCatalogResult($this->user, $tag);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/evaluation-catalog/{$assessment->id}")
        ->assertOk();

    $event = EvidenceDeletionEvent::query()->sole();
    expect($event->source_type)->toBe('assessment_result')
        ->and($event->competency_type)->toBe('skill_tag')
        ->and($event->competency_key)->toBe($tag->id)
        ->and($event->subject_id)->toBe($this->user->id);

    expect(EvidenceDeletionActorLink::query()->where('event_id', $event->id)->sole()->actor_id)
        ->toBe($this->admin->id);
});

it('gives each deletion its own actor pseudonym even for the same admin', function (): void {
    // One assessment per user: two subjects for two independent sources.
    $other = User::factory()->create(['client_type' => 'personal']);
    $first = edrPersonality($this->user);
    edrOceanLink($first, 'O');
    $second = edrPersonality($other);
    edrOceanLink($second, 'C');

    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/assessments/{$first->id}")->assertOk();
    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/assessments/{$second->id}")->assertOk();

    $pseudonyms = EvidenceDeletionEvent::query()->pluck('actor_pseudonym');
    expect($pseudonyms)->toHaveCount(2)
        ->and($pseudonyms[0])->not->toBe($pseudonyms[1]);
});

it('records nothing when an unlinked source is deleted', function (): void {
    $source = edrPersonality($this->user);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/assessments/{$source->id}")
        ->assertOk();

    expect(EvidenceDeletionEvent::query()->count())->toBe(0)
        ->and(EvidenceDeletionActorLink::query()->count())->toBe(0);
});

it('skips the event when retention is disabled but still retires the link', function (): void {
    config(['progress.deletion_event_retention_months' => 0]);

    $source = edrPersonality($this->user);
    $link = edrOceanLink($source, 'O');

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/assessments/{$source->id}")
        ->assertOk();

    expect(CompetencyAssessmentLink::query()->find($link->id))->toBeNull()
        ->and(EvidenceDeletionEvent::query()->count())->toBe(0)
        ->and(EvidenceDeletionActorLink::query()->count())->toBe(0)
        ->and(Activity::query()->where('event', 'competency.evidence_link_changed')->count())->toBe(1);
});

it('rolls back events, link retirement and source delete atomically on retake failure', function (): void {
    $source = edrPersonality($this->user, ['results' => ['factors' => []]]);
    $link = edrOceanLink($source, 'O');

    PersonalityAssessment::deleting(fn () => throw new RuntimeException('boom'));

    edrRetake($this->user)->assertStatus(500);

    expect(EvidenceDeletionEvent::query()->count())->toBe(0)
        ->and(EvidenceDeletionActorLink::query()->count())->toBe(0)
        ->and(CompetencyAssessmentLink::query()->find($link->id))->not->toBeNull()
        ->and(PersonalityAssessment::query()->find($source->id))->not->toBeNull()
        ->and(Activity::query()->where('event', 'competency.evidence_link_changed')->count())->toBe(0);
});

it('keeps the privacy boundary while the operational audit coexists', function (): void {
    $tag = SkillTag::create(['name' => 'edr-'.Str::random(6), 'category' => 't']);
    [$assessment, $result, $link] = edrCatalogResult($this->user, $tag);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/evaluation-catalog/{$assessment->id}")
        ->assertOk();

    $row = (array) DB::table('evidence_deletion_events')->first();
    $json = json_encode($row);
    expect($json)->not->toContain($result->id)
        ->and($json)->not->toContain($link->id)
        ->and($json)->not->toContain($this->admin->id);

    // Both registers coexist: the operational audit still names source+actor.
    $activity = Activity::query()->where('event', 'competency.evidence_link_changed')->sole();
    expect($activity->properties['before']['source_id'])->toBe($result->id)
        ->and($activity->properties['actor_id'])->toBe($this->admin->id);
});
