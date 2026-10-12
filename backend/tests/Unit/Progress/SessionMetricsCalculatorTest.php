<?php

use App\Services\Progress\Calculators\SessionMetricsCalculator;
use App\Services\Progress\Support\MetricValue;
use Carbon\CarbonImmutable;

uses()->group('progress');

beforeEach(function () {
    $this->calc = new SessionMetricsCalculator;
    $this->now = CarbonImmutable::parse('2026-06-15T12:00:00Z');
});

it('counts only completed sessions with confirmed attendance in the calendar month', function () {
    $sessions = [
        ['id' => 's1', 'status' => 'completed', 'scheduled_at' => '2026-06-10T10:00:00Z'],
        ['id' => 's2', 'status' => 'completed', 'scheduled_at' => '2026-06-11T10:00:00Z'],
        ['id' => 's3', 'status' => 'scheduled', 'scheduled_at' => '2026-06-12T10:00:00Z'],
        ['id' => 's4', 'status' => 'completed', 'scheduled_at' => '2026-05-30T10:00:00Z'],
    ];
    $attendance = [
        's1' => ['u1' => ['attended' => true]],
        's2' => ['u1' => ['attended' => false]],
    ];

    $metric = $this->calc->realized($sessions, $attendance, 'u1', $this->now);

    expect($metric->value)->toBe(1)
        ->and($metric->state)->toBe(MetricValue::VALUE)
        ->and($metric->source)->toBe('sessions+attendance');
});

it('returns no_data when there are no sessions in the month', function () {
    $metric = $this->calc->realized([
        ['id' => 's1', 'status' => 'completed', 'scheduled_at' => '2026-05-01T00:00:00Z'],
    ], [], 'u1', $this->now);

    expect($metric->state)->toBe(MetricValue::NO_DATA);
});

it('applies the calendar month in the subject timezone', function () {
    $sessions = [
        ['id' => 's1', 'status' => 'completed', 'scheduled_at' => '2026-06-30T23:30:00Z'],
    ];
    $attendance = ['s1' => ['u1' => ['attended' => true]]];

    $utc = $this->calc->realized($sessions, $attendance, 'u1', $this->now, 'UTC');
    $madrid = $this->calc->realized($sessions, $attendance, 'u1', $this->now, 'Europe/Madrid');

    expect($utc->value)->toBe(1)
        ->and($madrid->state)->toBe(MetricValue::NO_DATA);
});

it('lists future scheduled sessions only', function () {
    $items = $this->calc->scheduled([
        ['id' => 's1', 'status' => 'scheduled', 'scheduled_at' => '2026-06-20T10:00:00Z'],
        ['id' => 's2', 'status' => 'scheduled', 'scheduled_at' => '2026-06-01T10:00:00Z'],
        ['id' => 's3', 'status' => 'completed', 'scheduled_at' => '2026-06-25T10:00:00Z'],
    ], $this->now);

    expect($items)->toHaveCount(1)
        ->and($items[0]['id'])->toBe('s1');
});
