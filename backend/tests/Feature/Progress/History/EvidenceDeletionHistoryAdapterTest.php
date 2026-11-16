<?php

use App\Actions\SetCompetencyAssessmentLinkAction;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\CompetencyAssessmentLink;
use App\Models\EvidenceDeletionEvent;
use App\Models\PersonalityAssessment;
use App\Models\SkillTag;
use App\Models\User;
use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use App\Services\Progress\History\CompetencyHistoryAdapter;
use App\Services\Progress\History\EvidenceDeletionHistoryAdapter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * T015.5 — minimal deletion events in the timeline (RF-015.5, CA-19.7,
 * docs/politica-retencion.md §4–§5). The deletion surfaces only through the
 * minimal event: hidden actor, whitelisted discriminators, expiry filter —
 * never the real actor, the pseudonym or any link/source identity.
 */
uses(RefreshDatabase::class)->group('progress');

beforeEach(function (): void {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');

    $this->subject = User::factory()->create(['client_type' => 'personal']);
    $this->admin = User::factory()->superAdmin()->create();
    $this->admin->assignRole('super_admin');

    $this->adapter = new EvidenceDeletionHistoryAdapter;
    $this->competencies = new CompetencyHistoryAdapter;
    $this->set = app(SetCompetencyAssessmentLinkAction::class);
});

function delHistIds(array $events): array
{
    return array_column($events, 'id');
}

function delHistSkillTag(string $name = 'Liderazgo'): SkillTag
{
    return SkillTag::create(['name' => $name, 'category' => 'test']);
}

function delHistCatalogResult(User $user): AssessmentResult
{
    $assessment = Assessment::create([
        'slug' => 'del-hist-'.Str::random(8),
        'title' => ['es' => 'Catálogo'],
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

function delHistPersonality(User $user): PersonalityAssessment
{
    return PersonalityAssessment::factory()->create([
        'user_id' => $user->id,
        'completed_at' => now(),
    ]);
}

it('emits one hidden-actor event per retired link at the true instant', function (): void {
    $source = delHistPersonality($this->subject);
    $linkO = CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($source)->forOceanFactor('O')
        ->create(['user_id' => $this->subject->id]);
    $linkC = CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($source)->forOceanFactor('C')
        ->create(['user_id' => $this->subject->id]);

    $instant = CarbonImmutable::parse('2026-11-15T16:00:00Z');
    $this->travelTo($instant);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/assessments/{$source->id}")
        ->assertOk();

    $rows = EvidenceDeletionEvent::all();
    expect($rows)->toHaveCount(2);

    $events = $this->adapter->dated($this->subject, null, 25);
    expect($events)->toHaveCount(2);

    $byKey = collect($events)->keyBy('before.competency_key');
    foreach (['O', 'C'] as $factor) {
        $event = $byKey->get($factor);
        expect($event)->not->toBeNull()
            ->and($event['type'])->toBe('competency.evidence_source_deleted')
            ->and($event['domain'])->toBe('competencies')
            ->and($event['actor'])->toBe(['kind' => 'hidden'])
            ->and($event['occurred_at']->eq($instant))->toBeTrue()
            ->and($event['subject_ref'])->toHaveKeys(['event_id'])
            ->and($event['after'])->toBeNull()
            ->and($event['incomplete'])->toBe([])
            ->and($event['before']['source_type'])->toBe('personality_assessment')
            ->and($event['before']['competency_type'])->toBe('ocean_factor')
            ->and($event['before']['competency_name'])->toBeNull();
    }

    $json = json_encode($events);
    foreach ($rows as $row) {
        expect($json)->not->toContain($row->actor_pseudonym);
    }
    expect($json)->not->toContain($this->admin->id)
        ->not->toContain($this->admin->name)
        ->not->toContain($source->id)
        ->not->toContain($linkO->id)
        ->not->toContain($linkC->id)
        ->not->toContain('expires_at')
        ->not->toContain('subject_id')
        ->not->toContain('pseudonym');
});

it('names skill_tag competencies and hides expired events without purging', function (): void {
    $tag = delHistSkillTag('Comunicación');
    $result = delHistCatalogResult($this->subject);
    CompetencyAssessmentLink::factory()
        ->forAssessmentResult($result)->forSkillTag($tag)
        ->create(['user_id' => $this->subject->id]);

    $this->travelTo(CarbonImmutable::parse('2026-11-15T16:00:00Z'));
    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/evaluation-catalog/{$result->assessment_id}")
        ->assertOk();

    $row = EvidenceDeletionEvent::sole();

    // T014.14: expires_at is a local timestamp; the cast reads the true
    // instant directly.
    $expiry = $row->expires_at;

    $this->travelTo($expiry->subMinute());
    $events = $this->adapter->dated($this->subject, null, 25);
    expect($events)->toHaveCount(1)
        ->and($events[0]['before']['competency_type'])->toBe('skill_tag')
        ->and($events[0]['before']['competency_key'])->toBe($tag->id)
        ->and($events[0]['before']['competency_name'])->toBe('Comunicación');

    // At exactly expires_at and after: hidden even though the row still
    // exists (the purge is a separate job — convention shared with the
    // audit endpoint and ExpireEvidenceDeletionEvents).
    $this->travelTo($expiry);
    expect($this->adapter->dated($this->subject, null, 25))->toBe([]);

    $this->travelTo($expiry->addDay());
    expect($this->adapter->dated($this->subject, null, 25))->toBe([]);
    expect(EvidenceDeletionEvent::query()->whereKey($row->id)->exists())->toBeTrue();
});

it('isolates deletion events strictly to the subject', function (): void {
    $other = User::factory()->create(['client_type' => 'personal']);
    $source = delHistPersonality($other);
    CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($source)->forOceanFactor('O')
        ->create(['user_id' => $other->id]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/assessments/{$source->id}")
        ->assertOk();

    expect($this->adapter->dated($this->subject, null, 25))->toBe([]);
});

it('shows the deletion exactly once across merged competency feeds', function (): void {
    $tag = delHistSkillTag();
    $result = delHistCatalogResult($this->subject);

    $link = $this->set->execute(
        $this->subject, $this->subject, null,
        'assessment_result', $result->id, 'skill_tag', $tag->id, now(),
    );

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/evaluation-catalog/{$result->assessment_id}")
        ->assertOk();

    $merged = ProgressHistoryCalculator::mergePage([
        $this->competencies->dated($this->subject, null, 25),
        $this->adapter->dated($this->subject, null, 25),
    ], null, 50)['data'];

    // One creation event, one minimal deletion event — the retirement of the
    // link never appears as a second removal.
    expect($merged)->toHaveCount(2);
    expect(collect($merged)->pluck('type')->all())->toBe([
        'competency.evidence_source_deleted',
        'competency.evidence_link_changed',
    ]);
    expect($merged[1]['subject_ref']['link_id'])->toBe($link->id);
});

it('orders with other adapters at the same true instant', function (): void {
    $tag = delHistSkillTag();
    $instant = CarbonImmutable::parse('2026-11-15T16:00:00Z');

    $first = delHistCatalogResult($this->subject);
    $this->set->execute(
        $this->subject, $this->subject, null,
        'assessment_result', $first->id,
        'skill_tag', $tag->id, $instant->subMinute(),
    );

    $source = delHistPersonality($this->subject);
    CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($source)->forOceanFactor('O')
        ->create(['user_id' => $this->subject->id]);

    $this->travelTo($instant);
    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/assessments/{$source->id}")
        ->assertOk();

    $second = delHistCatalogResult($this->subject);
    $this->set->execute(
        $this->subject, $this->subject, null,
        'assessment_result', $second->id,
        'skill_tag', $tag->id, $instant->addMinute(),
    );

    $merged = ProgressHistoryCalculator::mergePage([
        $this->competencies->dated($this->subject, null, 25),
        $this->adapter->dated($this->subject, null, 25),
    ], null, 50)['data'];

    expect(collect($merged)->pluck('type')->all())->toBe([
        'competency.evidence_link_changed',
        'competency.evidence_source_deleted',
        'competency.evidence_link_changed',
    ]);

    $sorted = $merged;
    usort($sorted, fn (array $a, array $b): int => ProgressHistoryCalculator::compare($a, $b));
    expect(delHistIds($sorted))->toBe(delHistIds($merged));
});

it('pages through ties exactly once in compare() order', function (): void {
    $result = delHistCatalogResult($this->subject);
    foreach (['a', 'b', 'c'] as $suffix) {
        CompetencyAssessmentLink::factory()
            ->forAssessmentResult($result)
            ->forSkillTag(delHistSkillTag("tag-{$suffix}"))
            ->create(['user_id' => $this->subject->id]);
    }

    $this->travelTo(CarbonImmutable::parse('2026-11-15T16:00:00Z'));
    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/evaluation-catalog/{$result->assessment_id}")
        ->assertOk();

    $expected = delHistIds(
        ProgressHistoryCalculator::mergePage([$this->adapter->dated($this->subject, null, 50)], null, 50)['data'],
    );
    expect($expected)->toHaveCount(3);

    foreach ([1, 2] as $limit) {
        $seen = [];
        $after = null;
        $guard = 0;
        do {
            $page = ProgressHistoryCalculator::mergePage(
                [$this->adapter->dated($this->subject, $after, $limit)],
                $after,
                $limit,
            );
            $seen = array_merge($seen, delHistIds($page['data']));
            $after = $page['next_cursor'] !== null
                ? ProgressHistoryCalculator::decodeCursor($page['next_cursor'])
                : null;
        } while ($after !== null && ++$guard < 30);

        expect($seen)->toBe($expected);
    }
});

it('uses a constant number of queries per dated() call', function (): void {
    $result = delHistCatalogResult($this->subject);
    CompetencyAssessmentLink::factory()
        ->forAssessmentResult($result)->forSkillTag(delHistSkillTag())
        ->create(['user_id' => $this->subject->id]);
    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/evaluation-catalog/{$result->assessment_id}")
        ->assertOk();

    $count = function (): int {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $this->adapter->dated($this->subject, null, 25);

        return $queries;
    };

    $small = $count();

    $more = delHistCatalogResult($this->subject);
    foreach (range(1, 4) as $i) {
        CompetencyAssessmentLink::factory()
            ->forAssessmentResult($more)
            ->forSkillTag(delHistSkillTag("extra-{$i}"))
            ->create(['user_id' => $this->subject->id]);
    }
    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/evaluation-catalog/{$more->assessment_id}")
        ->assertOk();

    $big = $count();

    expect($big)->toBe($small)->and($big)->toBeLessThanOrEqual(2);
});

it('returns no undated events', function (): void {
    expect($this->adapter->undated($this->subject, 10))->toBe([]);
});
