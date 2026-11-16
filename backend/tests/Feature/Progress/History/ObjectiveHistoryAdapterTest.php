<?php

use App\Models\MediaAsset;
use App\Models\Objective;
use App\Models\ObjectiveEvidence;
use App\Models\User;
use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use App\Services\Progress\History\ObjectiveEvidenceHistoryAdapter;
use App\Services\Progress\History\ObjectiveHistoryAdapter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * T015.2 — history adapters for objectives (activity_log 'objective') and
 * objective evidences (RF-015.2/.7/.8, plan §1-§2, D-2). Only INDIVIDUAL
 * objectives owned by the subject feed the timeline; soft-deleted objectives
 * keep their verified history. Nothing is inferred: an `updated` without a
 * status change produces no entry and whitelisted fields only are emitted.
 */
uses(RefreshDatabase::class)->group('progress');

beforeEach(function (): void {
    $this->subject = User::factory()->create();
    $this->objectives = new ObjectiveHistoryAdapter;
    $this->evidences = new ObjectiveEvidenceHistoryAdapter;
});

function histIds(array $events): array
{
    return array_column($events, 'id');
}

function histAll(object $adapter, User $subject): array
{
    return $adapter->dated($subject, null, 50);
}

it('emits created and status_changed events with actor, dates and whitelisted fields', function (): void {
    $this->actingAs($this->subject);

    $this->travelTo(CarbonImmutable::parse('2026-11-15T16:00:00Z'));
    $objective = Objective::factory()->individual($this->subject)->create([
        'title' => 'Objetivo uno',
        'description' => 'texto',
        'success_criteria' => 'criterio',
        'status' => 'not_started',
    ]);

    $this->travelTo(CarbonImmutable::parse('2026-11-15T17:00:00Z'));
    $objective->update(['status' => 'in_progress']);

    $this->travelTo(CarbonImmutable::parse('2026-11-15T18:00:00Z'));
    $objective->update(['status' => 'achieved']);

    $events = histAll($this->objectives, $this->subject);

    expect($events)->toHaveCount(3);

    expect($events[0]['type'])->toBe('objective.status_changed')
        ->and($events[0]['domain'])->toBe('objectives')
        ->and($events[0]['before'])->toBe(['status' => 'in_progress'])
        ->and($events[0]['after'])->toBe(['status' => 'achieved'])
        ->and($events[0]['actor'])->toBe(['id' => $this->subject->id, 'name' => $this->subject->name])
        ->and($events[0]['subject_ref'])->toBe(['objective_id' => $objective->id, 'title' => 'Objetivo uno'])
        ->and($events[0]['id'])->toStartWith('objective_activity:')
        ->and($events[0]['occurred_at']->eq(CarbonImmutable::parse('2026-11-15T18:00:00Z')))->toBeTrue();

    expect($events[1]['before'])->toBe(['status' => 'not_started'])
        ->and($events[1]['after'])->toBe(['status' => 'in_progress']);

    expect($events[2]['type'])->toBe('objective.created')
        ->and($events[2]['before'])->toBeNull()
        ->and($events[2]['after'])->toBe(['status' => 'not_started']);

    $activityCreatedAt = DB::table('activity_log')
        ->where('event', 'created')
        ->where('subject_id', $objective->id)
        ->value('created_at');
    expect($events[2]['occurred_at']->eq(CarbonImmutable::parse($activityCreatedAt)))->toBeTrue();

    foreach ($events as $event) {
        $json = json_encode($event);
        expect($json)->not->toContain('success_criteria')
            ->not->toContain('due_at')
            ->not->toContain('owner_id')
            ->not->toContain('description')
            ->not->toContain('texto')
            ->not->toContain('criterio');
    }
});

it('produces no event for an update without a status change', function (): void {
    $this->actingAs($this->subject);
    $objective = Objective::factory()->individual($this->subject)->create(['status' => 'not_started']);

    $objective->update(['due_at' => '2027-01-01', 'success_criteria' => 'otro criterio']);

    $events = histAll($this->objectives, $this->subject);

    expect($events)->toHaveCount(1);
    expect($events[0]['type'])->toBe('objective.created');
});

it('excludes objectives of other users and collective objectives', function (): void {
    $this->actingAs($this->subject);
    $stranger = User::factory()->create();
    Objective::factory()->individual($stranger)->create()->update(['status' => 'in_progress']);
    Objective::factory()->collective('family_group')->create()->update(['status' => 'in_progress']);

    expect(histAll($this->objectives, $this->subject))->toBe([]);
    expect(histAll($this->evidences, $this->subject))->toBe([]);
});

it('keeps the verified history of a soft-deleted objective', function (): void {
    $this->actingAs($this->subject);
    $objective = Objective::factory()->individual($this->subject)->create();
    $objective->update(['status' => 'in_progress']);
    $objective->delete();

    $events = histAll($this->objectives, $this->subject);

    expect($events)->toHaveCount(2);
    expect($events[0]['type'])->toBe('objective.status_changed');
    expect($events[1]['type'])->toBe('objective.created');
});

it('reports a null actor as incomplete instead of inventing one', function (): void {
    // No actingAs: the activity is written without a causer.
    Objective::factory()->individual($this->subject)->create();

    $event = histAll($this->objectives, $this->subject)[0];

    expect($event['actor'])->toBeNull();
    expect($event['incomplete'])->toContain('actor');
});

it('emits evidence_added events with has_attachment and author, never the note or asset', function (): void {
    $this->actingAs($this->subject);
    $objective = Objective::factory()->individual($this->subject)->create();
    $asset = MediaAsset::factory()->create();

    $withAttachment = ObjectiveEvidence::factory()->create([
        'objective_id' => $objective->id,
        'user_id' => $this->subject->id,
        'media_asset_id' => $asset->id,
        'note' => 'nota privada',
    ]);
    $without = ObjectiveEvidence::factory()->create([
        'objective_id' => $objective->id,
        'user_id' => $this->subject->id,
        'media_asset_id' => null,
    ]);

    $events = histAll($this->evidences, $this->subject);

    expect($events)->toHaveCount(2);

    $byEvidence = collect($events)->keyBy(fn (array $e): string => $e['subject_ref']['evidence_id']);

    $a = $byEvidence->get($withAttachment->id);
    expect($a['type'])->toBe('objective.evidence_added')
        ->and($a['domain'])->toBe('objectives')
        ->and($a['after'])->toBe(['has_attachment' => true])
        ->and($a['before'])->toBeNull()
        ->and($a['actor'])->toBe(['id' => $this->subject->id, 'name' => $this->subject->name])
        ->and($a['subject_ref']['objective_id'])->toBe($objective->id)
        ->and($a['subject_ref']['title'])->toBe($objective->title)
        ->and($a['id'])->toBe("objective_evidence:{$withAttachment->id}");

    expect($byEvidence->get($without->id)['after'])->toBe(['has_attachment' => false]);

    foreach ($events as $event) {
        $json = json_encode($event);
        expect($json)->not->toContain('note')
            ->not->toContain('media_asset_id')
            ->not->toContain('nota privada')
            ->not->toContain($asset->path);
    }
});

it('excludes evidences belonging to other users’ objectives', function (): void {
    $stranger = User::factory()->create();
    $strangerObjective = Objective::factory()->individual($stranger)->create();
    ObjectiveEvidence::factory()->create([
        'objective_id' => $strangerObjective->id,
        'user_id' => $stranger->id,
    ]);

    expect(histAll($this->evidences, $this->subject))->toBe([]);
});

it('returns no undated events from either adapter', function (): void {
    expect($this->objectives->undated($this->subject, 10))->toBe([]);
    expect($this->evidences->undated($this->subject, 10))->toBe([]);
});

it('pages through both adapters exactly once in compare() order', function (): void {
    $this->actingAs($this->subject);

    // Three same-instant events across both adapters at T1.
    $this->travelTo(CarbonImmutable::parse('2026-11-15T16:00:00Z'));
    $objective = Objective::factory()->individual($this->subject)->create();
    $objective->update(['status' => 'in_progress']);
    ObjectiveEvidence::factory()->create(['objective_id' => $objective->id, 'user_id' => $this->subject->id]);

    $this->travelTo(CarbonImmutable::parse('2026-11-15T17:00:00Z'));
    $objective->update(['status' => 'achieved']);
    ObjectiveEvidence::factory()->create(['objective_id' => $objective->id, 'user_id' => $this->subject->id]);

    $this->travelTo(CarbonImmutable::parse('2026-11-15T18:00:00Z'));
    Objective::factory()->individual($this->subject)->create();

    $expected = histIds(ProgressHistoryCalculator::mergePage([
        histAll($this->objectives, $this->subject),
        histAll($this->evidences, $this->subject),
    ], null, 50)['data']);
    expect($expected)->toHaveCount(6);

    foreach ([1, 2] as $limit) {
        $seen = [];
        $after = null;
        $guard = 0;

        do {
            $page = ProgressHistoryCalculator::mergePage([
                $this->objectives->dated($this->subject, $after, $limit),
                $this->evidences->dated($this->subject, $after, $limit),
            ], $after, $limit);

            $seen = array_merge($seen, histIds($page['data']));
            $after = $page['next_cursor'] !== null
                ? ProgressHistoryCalculator::decodeCursor($page['next_cursor'])
                : null;
        } while ($after !== null && ++$guard < 20);

        expect($seen)->toBe($expected);
    }
});

it('honours limit + 1 and orders exactly like compare(), including the 9/10 padding boundary', function (): void {
    $this->actingAs($this->subject);
    $this->travelTo(CarbonImmutable::parse('2026-11-15T16:00:00Z'));

    // Cross the 9/10 digit boundary on purpose: unpadded string order would
    // sort '9' before '10', padded order must stay numeric.
    DB::statement('ALTER SEQUENCE activity_log_id_seq RESTART WITH 9');
    Objective::factory()->individual($this->subject)->count(3)->create();

    $events = $this->objectives->dated($this->subject, null, 1);
    expect(count($events))->toBeLessThanOrEqual(2);

    $all = histAll($this->objectives, $this->subject);
    $ids = histIds($all);

    $expected = array_map(
        fn (int $n): string => 'objective_activity:' . str_pad((string) $n, 20, '0', STR_PAD_LEFT),
        [11, 10, 9],
    );
    expect($ids)->toBe($expected);

    $sorted = $all;
    usort($sorted, fn (array $a, array $b): int => ProgressHistoryCalculator::compare($a, $b));
    expect(histIds($sorted))->toBe($ids);
});

it('uses a constant number of queries per dated() call', function (): void {
    $this->actingAs($this->subject);
    $objective = Objective::factory()->individual($this->subject)->create();
    ObjectiveEvidence::factory()->create(['objective_id' => $objective->id, 'user_id' => $this->subject->id]);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $this->objectives->dated($this->subject, null, 25);
    $smallRun = $queries;

    Objective::factory()->individual($this->subject)->count(4)->create();
    $objective->update(['status' => 'in_progress']);
    ObjectiveEvidence::factory()->count(3)->create(['objective_id' => $objective->id, 'user_id' => $this->subject->id]);

    $queries = 0;
    $this->objectives->dated($this->subject, null, 25);
    $bigRun = $queries;

    $queries = 0;
    $this->evidences->dated($this->subject, null, 25);
    $evidenceRun = $queries;

    expect($smallRun)->toBeLessThanOrEqual(4);
    expect($bigRun)->toBe($smallRun);
    expect($evidenceRun)->toBeLessThanOrEqual(4);
});
