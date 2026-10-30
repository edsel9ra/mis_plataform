<?php

use App\Services\Scheduling\Rules\OverlapDetector;
use Carbon\CarbonImmutable;

it('treats session intervals as half-open so adjacent sessions do not overlap', function (): void {
    $start = CarbonImmutable::parse('2026-10-12T10:00:00Z');

    expect(OverlapDetector::overlaps(
        $start,
        $start->addMinutes(30),
        $start->addMinutes(30),
        $start->addMinutes(60),
    ))->toBeFalse();
});

it('detects a one-minute intersection between intervals', function (): void {
    $start = CarbonImmutable::parse('2026-10-12T10:00:00Z');

    expect(OverlapDetector::overlaps(
        $start,
        $start->addMinutes(30),
        $start->addMinutes(29),
        $start->addMinutes(60),
    ))->toBeTrue();
});

it('returns conflicts only for shared participants and excludes canceled or not-completed sessions', function (): void {
    $candidate = [
        'start' => CarbonImmutable::parse('2026-10-12T10:00:00Z'),
        'end' => CarbonImmutable::parse('2026-10-12T11:00:00Z'),
        'participants' => ['mentor-1', 'mentee-1', 'member-1'],
    ];
    $now = CarbonImmutable::parse('2026-10-12T09:00:00Z');
    $existing = [
        [
            'id' => 'session-shared',
            'start' => CarbonImmutable::parse('2026-10-12T10:30:00Z'),
            'end' => CarbonImmutable::parse('2026-10-12T11:30:00Z'),
            'status' => 'scheduled',
            'participants' => ['member-1', 'other-mentee'],
        ],
        [
            'id' => 'session-canceled',
            'start' => CarbonImmutable::parse('2026-10-12T10:30:00Z'),
            'end' => CarbonImmutable::parse('2026-10-12T11:30:00Z'),
            'status' => 'canceled',
            'participants' => ['mentor-1'],
        ],
        [
            'id' => 'session-not-completed',
            'start' => CarbonImmutable::parse('2026-10-12T10:30:00Z'),
            'end' => CarbonImmutable::parse('2026-10-12T11:30:00Z'),
            'status' => 'not_completed',
            'participants' => ['mentee-1'],
        ],
        [
            'id' => 'session-unrelated',
            'start' => CarbonImmutable::parse('2026-10-12T10:30:00Z'),
            'end' => CarbonImmutable::parse('2026-10-12T11:30:00Z'),
            'status' => 'scheduled',
            'participants' => ['someone-else'],
        ],
    ];

    expect(OverlapDetector::conflicts($candidate, $existing, $now))->toBe([
        ['session_id' => 'session-shared', 'user_id' => 'member-1'],
    ]);
});
