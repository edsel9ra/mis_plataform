<?php

use App\Actions\RemoveCompetencyAssessmentLinkAction;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\CompetencyAssessmentLink;
use App\Models\SkillTag;
use App\Models\User;
use App\Services\Progress\History\CompetencyHistoryAdapter;
use App\Services\Progress\History\ProgressHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/**
 * T015.10 — retirements recorded before T015.4 have no `reason` marker, so
 * the competency feed exposes the real deleter. The
 * 2026_11_16_000003 migration marks them `source_deleted` when an
 * evidence_deletion_events row matches subject + source + competency +
 * exact instant (RetireEvidenceLinksForSourceAction passes the same $now
 * to both). Manual removals and unrelated rows stay untouched; down()
 * removes only the backfilled keys.
 */
uses(RefreshDatabase::class)->group('progress');

beforeEach(function (): void {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');

    $this->subject = User::factory()->create(['client_type' => 'personal']);
    $this->admin = User::factory()->superAdmin()->create();
    $this->admin->assignRole('super_admin');

    $this->remove = app(RemoveCompetencyAssessmentLinkAction::class);
    $this->adapter = new CompetencyHistoryAdapter;
});

function sdrSkillTag(string $name = 'Liderazgo'): SkillTag
{
    return SkillTag::create(['name' => $name, 'category' => 'test']);
}

function sdrCatalogResult(User $user): AssessmentResult
{
    $assessment = Assessment::create([
        'slug' => 'sdr-'.Str::random(8),
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

function sdrLink(User $user, AssessmentResult $result, SkillTag $tag): CompetencyAssessmentLink
{
    return CompetencyAssessmentLink::factory()
        ->forAssessmentResult($result)
        ->forSkillTag($tag)
        ->create(['user_id' => $user->id]);
}

function sdrRetirement(int|string $linkId): ?Activity
{
    return Activity::query()
        ->where('event', 'competency.evidence_link_changed')
        ->get()
        ->firstWhere(fn (Activity $a) => $a->properties['link_id'] === $linkId);
}

function sdrMigration(): object
{
    return require database_path('migrations/2026_11_16_000003_backfill_source_deleted_reason_on_evidence_link_retirements.php');
}

function sdrStripReason(int|string $activityId): void
{
    DB::table('activity_log')->where('id', $activityId)
        ->update(['properties' => DB::raw("(properties::jsonb - 'reason')::json")]);
}

function sdrShiftOccurredAt(int|string $activityId, string $shift): void
{
    DB::statement(
        "UPDATE activity_log
            SET properties = (properties::jsonb || jsonb_build_object(
                'occurred_at', (properties::jsonb ->> 'occurred_at')::timestamptz + interval '{$shift}'
            ))::json
            WHERE id = ?",
        [$activityId],
    );
}

/**
 * Builds the full legacy fixture and returns the relevant Activity models:
 * [legacy (reason stripped), manual, native] plus the link that got deleted.
 *
 * @return array{legacy: Activity, manual: Activity, native: Activity}
 */
function sdrFixture(object $test): array
{
    $tag = sdrSkillTag();

    // Real admin deletion → deletion event + retirement marked source_deleted.
    $deletedResult = sdrCatalogResult($test->subject);
    $deletedLink = sdrLink($test->subject, $deletedResult, $tag);
    $test->actingAs($test->admin)
        ->deleteJson("/api/v1/admin/evaluation-catalog/{$deletedResult->assessment_id}")
        ->assertOk();
    $legacy = sdrRetirement($deletedLink->id);
    expect($legacy)->not->toBeNull()
        ->and($legacy->properties['reason'])->toBe('source_deleted');

    // Mimic pre-T015.4 data: drop the marker, keep everything else.
    sdrStripReason($legacy->id);
    $legacy->refresh();

    // Manual removal of a link on a different competency — no deletion
    // event matches it, so the backfill must never mark it.
    $manualTag = sdrSkillTag('Comunicación');
    $manualResult = sdrCatalogResult($test->subject);
    $manualLink = sdrLink($test->subject, $manualResult, $manualTag);
    $test->remove->execute($test->subject, $test->subject, $manualLink, now());
    $manual = sdrRetirement($manualLink->id);
    expect($manual->properties->has('reason'))->toBeFalse();

    // Second real deletion — retirement keeps its native reason.
    $nativeResult = sdrCatalogResult($test->subject);
    $nativeLink = sdrLink($test->subject, $nativeResult, $tag);
    $test->actingAs($test->admin)
        ->deleteJson("/api/v1/admin/evaluation-catalog/{$nativeResult->assessment_id}")
        ->assertOk();
    $native = sdrRetirement($nativeLink->id);
    expect($native)->not->toBeNull()
        ->and($native->properties['reason'])->toBe('source_deleted')
        ->and($native->properties->has('reason_backfilled'))->toBeFalse();

    return ['legacy' => $legacy, 'manual' => $manual, 'native' => $native];
}

it('leaks the deleter on legacy retirements before the backfill', function (): void {
    ['legacy' => $legacy] = sdrFixture($this);

    $events = $this->adapter->dated($this->subject, null, 25);
    $retirement = collect($events)
        ->firstWhere(fn (array $e) => ($e['subject_ref']['link_id'] ?? null) === $legacy->properties['link_id']);

    expect($retirement)->not->toBeNull()
        ->and($retirement['actor'])->toBe(['id' => $this->admin->id, 'name' => $this->admin->name]);
});

it('marks legacy retirements source_deleted without touching other rows', function (): void {
    ['legacy' => $legacy, 'manual' => $manual, 'native' => $native] = sdrFixture($this);

    $legacyProps = $legacy->properties->toArray();
    $manualProps = $manual->properties->toArray();
    $nativeProps = $native->properties->toArray();

    sdrMigration()->up();

    $legacy->refresh();
    expect($legacy->properties['reason'])->toBe('source_deleted')
        ->and($legacy->properties['reason_backfilled'])->toBeTrue();
    expect(collect($legacy->properties->toArray())->except(['reason', 'reason_backfilled'])->all())
        ->toBe($legacyProps);

    $manual->refresh();
    expect($manual->properties->toArray())->toBe($manualProps);

    $native->refresh();
    expect($native->properties->toArray())->toBe($nativeProps);

    // The feed no longer exposes the legacy retirement or the admin.
    $events = $this->adapter->dated($this->subject, null, 25);
    expect(collect($events)
        ->firstWhere(fn (array $e) => ($e['subject_ref']['link_id'] ?? null) === $legacy->properties['link_id']))
        ->toBeNull();

    $page = app(ProgressHistoryService::class)->page($this->subject, null, 25);
    $json = json_encode($page);
    expect($json)->not->toContain($this->admin->id)
        ->not->toContain($this->admin->name);
});

it('is idempotent when run twice', function (): void {
    ['legacy' => $legacy, 'manual' => $manual, 'native' => $native] = sdrFixture($this);

    sdrMigration()->up();
    $before = Activity::query()
        ->where('event', 'competency.evidence_link_changed')
        ->orderBy('id')
        ->get()
        ->map(fn (Activity $a) => $a->properties->toArray())
        ->all();

    sdrMigration()->up();

    $after = Activity::query()
        ->where('event', 'competency.evidence_link_changed')
        ->orderBy('id')
        ->get()
        ->map(fn (Activity $a) => $a->properties->toArray())
        ->all();

    expect($after)->toBe($before);
});

it('down() removes only the backfilled keys and restores the legacy shape', function (): void {
    ['legacy' => $legacy, 'manual' => $manual, 'native' => $native] = sdrFixture($this);

    $legacyProps = $legacy->properties->toArray();
    $manualProps = $manual->properties->toArray();
    $nativeProps = $native->properties->toArray();

    sdrMigration()->up();
    sdrMigration()->down();

    $legacy->refresh();
    expect($legacy->properties->toArray())->toBe($legacyProps);

    $manual->refresh();
    expect($manual->properties->toArray())->toBe($manualProps);

    $native->refresh();
    expect($native->properties->toArray())->toBe($nativeProps);
});

it('leaves unmarked a retirement whose instant does not match any deletion event', function (): void {
    $tag = sdrSkillTag();
    $result = sdrCatalogResult($this->subject);
    $link = sdrLink($this->subject, $result, $tag);
    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/evaluation-catalog/{$result->assessment_id}")
        ->assertOk();
    $legacy = sdrRetirement($link->id);
    sdrStripReason($legacy->id);
    sdrShiftOccurredAt($legacy->id, '1 second');
    $legacy->refresh();
    $props = $legacy->properties->toArray();

    sdrMigration()->up();

    $legacy->refresh();
    expect($legacy->properties->toArray())->toBe($props)
        ->and($legacy->properties->has('reason'))->toBeFalse()
        ->and($legacy->properties->has('reason_backfilled'))->toBeFalse();
});
