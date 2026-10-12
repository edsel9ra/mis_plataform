<?php

use App\Services\Progress\Calculators\ObjectiveMetricsCalculator;
use App\Services\Progress\Support\MetricValue;
use Carbon\CarbonImmutable;

uses()->group('progress');

beforeEach(function () {
    $this->calc = new ObjectiveMetricsCalculator;
    $this->now = CarbonImmutable::parse('2026-06-15T12:00:00Z');
});

it('returns no_data when there are no objectives', function () {
    $metric = $this->calc->achievement([]);

    expect($metric->state)->toBe(MetricValue::NO_DATA)
        ->and($metric->value)->toBeNull()
        ->and($metric->denominator)->toBe(0);
});

it('returns zero when there are objectives but none achieved', function () {
    $metric = $this->calc->achievement([
        ['status' => 'not_started'],
        ['status' => 'in_progress'],
    ]);

    expect($metric->state)->toBe(MetricValue::ZERO)
        ->and($metric->value)->toBe(0)
        ->and($metric->numerator)->toBe(0)
        ->and($metric->denominator)->toBe(2);
});

it('keeps abandoned objectives in the denominator', function () {
    $metric = $this->calc->achievement([
        ['status' => 'achieved'],
        ['status' => 'abandoned'],
    ]);

    expect($metric->value)->toBe(50)
        ->and($metric->numerator)->toBe(1)
        ->and($metric->denominator)->toBe(2);
});

it('counts objectives by status', function () {
    $counts = $this->calc->byStatus([
        ['status' => 'achieved'],
        ['status' => 'abandoned'],
        ['status' => 'achieved'],
    ]);

    expect($counts)->toBe([
        'not_started' => 0,
        'in_progress' => 0,
        'achieved' => 2,
        'abandoned' => 1,
    ]);
});

it('treats past-due pending objectives as overdue, never achieved ones', function () {
    $past = '2026-06-01T00:00:00Z';

    expect($this->calc->isOverdue(['status' => 'in_progress', 'due_at' => $past], $this->now))->toBeTrue()
        ->and($this->calc->isOverdue(['status' => 'achieved', 'due_at' => $past], $this->now))->toBeFalse()
        ->and($this->calc->isOverdue(['status' => 'not_started', 'due_at' => null], $this->now))->toBeFalse();
});

it('classifies objectives into pending, overdue, achieved and abandoned', function () {
    $classification = $this->calc->classify([
        ['status' => 'not_started', 'due_at' => '2026-06-01T00:00:00Z'],
        ['status' => 'in_progress', 'due_at' => '2026-07-01T00:00:00Z'],
        ['status' => 'achieved', 'due_at' => '2026-01-01T00:00:00Z'],
        ['status' => 'abandoned', 'due_at' => null],
    ], $this->now);

    expect($classification)->toBe([
        'pending' => 2,
        'overdue' => 1,
        'achieved' => 1,
        'abandoned' => 1,
    ]);
});
