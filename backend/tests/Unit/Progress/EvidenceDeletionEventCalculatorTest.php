<?php

use App\Models\EvidenceDeletionEvent;
use App\Services\Progress\Calculators\EvidenceDeletionEventCalculator;
use Carbon\CarbonImmutable;

/**
 * T014.10 — pure minimal-deletion-event helpers. No database, clock or
 * randomness inside the calculator; retention config is normalized here so
 * a disabled retention is indistinguishable from a missing one.
 */

it('normalizes a positive integer retention in months', function (mixed $configured, ?int $expected): void {
    expect(EvidenceDeletionEventCalculator::retentionMonths($configured))->toBe($expected);
})->with([
    [12, 12],
    ['12', 12],
    [1, 1],
    [0, null],
    ['0', null],
    [null, null],
    ['', null],
    [-1, null],
    ['abc', null],
    [12.5, null],
]);

it('honours the configured default retention of 12 months', function (): void {
    expect(config('progress.deletion_event_retention_months'))->toBe(12);
    expect(EvidenceDeletionEventCalculator::retentionMonths(
        config('progress.deletion_event_retention_months'),
    ))->toBe(12);
});

it('adds retention months without overflowing into the next month', function (): void {
    $occurred = CarbonImmutable::parse('2025-03-15T10:00:00Z');

    expect(EvidenceDeletionEventCalculator::expiresAt($occurred, 12)->toIso8601String())
        ->toBe('2026-03-15T10:00:00+00:00');

    $leapDay = CarbonImmutable::parse('2024-02-29T10:00:00Z');
    expect(EvidenceDeletionEventCalculator::expiresAt($leapDay, 12)->toIso8601String())
        ->toBe('2025-02-28T10:00:00+00:00');
});

it('builds a minimal event with only the whitelisted link fields', function (): void {
    $now = CarbonImmutable::parse('2026-11-14T12:00:00Z');
    $link = [
        'id' => 'link-1',
        'link_id' => 'link-1',
        'source_id' => 'result-9',
        'source_type' => 'assessment_result',
        'competency_type' => 'ocean_factor',
        'competency_key' => 'C',
        'actor_id' => 'actor-1',
        'answers' => [['question_id' => 1]],
        'score' => 80,
        'raw_scores' => ['O' => 88],
        'results' => ['factors' => ['C' => 82]],
        'level' => 'advanced',
    ];

    $event = EvidenceDeletionEventCalculator::minimalEvent($link, 'subject-1', $now, 12, 'pseudonym-uuid');

    expect(array_keys($event))->toBe([
        'subject_id',
        'source_type',
        'competency_type',
        'competency_key',
        'change_type',
        'actor_pseudonym',
        'occurred_at',
        'expires_at',
    ]);
    expect($event['subject_id'])->toBe('subject-1');
    expect($event['source_type'])->toBe('assessment_result');
    expect($event['competency_type'])->toBe('ocean_factor');
    expect($event['competency_key'])->toBe('C');
    expect($event['change_type'])->toBe(EvidenceDeletionEvent::CHANGE_SOURCE_DELETED);
    expect($event['actor_pseudonym'])->toBe('pseudonym-uuid');
    expect($event['occurred_at'])->toBe($now);
    expect($event['expires_at']->toIso8601String())->toBe('2027-11-14T12:00:00+00:00');

    $json = json_encode($event);
    foreach (['source_id', 'link_id', 'answers', 'score', 'raw_scores', 'results', 'level', 'actor_id'] as $forbidden) {
        expect($json)->not->toContain('"'.$forbidden.'"');
    }

    // The caller's array is not mutated.
    expect($link['actor_id'])->toBe('actor-1');
});
