<?php

use App\Services\Scheduling\Rules\DuplicateDetector;
use Carbon\CarbonImmutable;

it('normalizes title by trimming, collapsing whitespace, and ignoring case', function (): void {
    expect(DuplicateDetector::normalizeTitle("  Sesión\t  de   Mentoría \n"))
        ->toBe('sesión de mentoría');
});

it('finds an identical relationship, instant, duration, and normalized title', function (): void {
    $start = CarbonImmutable::parse('2026-10-12T10:00:00Z');
    $candidate = [
        'relationship_id' => 'relationship-1',
        'start' => $start,
        'duration_minutes' => 60,
        'title' => "  Sesión   de Mentoría ",
    ];
    $existing = [[
        'id' => 'session-existing',
        'relationship_id' => 'relationship-1',
        'start' => $start->setTimezone('America/Mexico_City'),
        'duration_minutes' => 60,
        'title' => "sesión\tDE mentoría",
    ]];
    $now = CarbonImmutable::parse('2026-10-12T09:00:00Z');

    expect(DuplicateDetector::isDuplicate($candidate, $existing, $now))
        ->toBe('session-existing');
});

it('does not treat a different relationship, instant, duration, or normalized title as duplicate', function (): void {
    $start = CarbonImmutable::parse('2026-10-12T10:00:00Z');
    $candidate = [
        'relationship_id' => 'relationship-1',
        'start' => $start,
        'duration_minutes' => 60,
        'title' => 'Sesión inicial',
    ];
    $existing = [[
        'id' => 'session-other',
        'relationship_id' => 'relationship-2',
        'start' => $start->addSeconds(1),
        'duration_minutes' => 30,
        'title' => 'Sesión de seguimiento',
    ]];
    $now = CarbonImmutable::parse('2026-10-12T09:00:00Z');

    expect(DuplicateDetector::isDuplicate($candidate, $existing, $now))->toBeNull();
});
