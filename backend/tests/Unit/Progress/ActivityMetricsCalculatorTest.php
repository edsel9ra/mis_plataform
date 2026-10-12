<?php

use App\Services\Progress\Calculators\ActivityMetricsCalculator;
use Carbon\CarbonImmutable;

uses()->group('progress');

beforeEach(function () {
    $this->calc = new ActivityMetricsCalculator;
    $this->now = CarbonImmutable::parse('2026-06-15T12:00:00Z');
});

it('excludes canceled and template challenges from pending and completed', function () {
    $result = $this->calc->classify([
        ['id' => 'c1', 'status' => 'assigned'],
        ['id' => 'c2', 'status' => 'canceled'],
        ['id' => 'c3', 'status' => 'template'],
        ['id' => 'c4', 'status' => 'completed'],
    ], [], $this->now);

    expect($result['pending_count'])->toBe(1)
        ->and($result['completed'])->toBe(1)
        ->and($result['total_records'])->toBe(2);
});

it('never marks an activity without a due date as overdue', function () {
    $result = $this->calc->classify([
        ['id' => 'c1', 'status' => 'in_progress', 'due_at' => null],
        ['id' => 'c2', 'status' => 'assigned', 'due_at' => '2026-06-01T00:00:00Z'],
        ['id' => 'c3', 'status' => 'assigned', 'due_at' => '2026-07-01T00:00:00Z'],
    ], [], $this->now);

    expect($result['overdue'])->toBe(1)
        ->and($result['pending_count'])->toBe(3);
});

it('includes unfinished learning modules as pending', function () {
    $result = $this->calc->classify([], [
        ['id' => 'p1', 'status' => 'in_progress'],
        ['id' => 'p2', 'status' => 'completed'],
    ], $this->now);

    expect($result['pending_count'])->toBe(1)
        ->and($result['pending'][0]['type'])->toBe('learning_module')
        ->and($result['completed'])->toBe(1);
});
