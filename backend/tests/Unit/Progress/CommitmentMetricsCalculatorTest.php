<?php

use App\Services\Progress\Calculators\CommitmentMetricsCalculator;
use Carbon\CarbonImmutable;

uses()->group('progress');

beforeEach(function () {
    $this->calc = new CommitmentMetricsCalculator;
    $this->now = CarbonImmutable::parse('2026-06-15T12:00:00Z');
});

it('includes activity and objective deadlines inside the 7-day window only', function () {
    $items = $this->calc->build([], [
        ['id' => 'c1', 'title' => 'Inside', 'due_at' => '2026-06-20T10:00:00Z'],
        ['id' => 'c2', 'title' => 'Outside', 'due_at' => '2026-06-30T10:00:00Z'],
        ['id' => 'c3', 'title' => 'Past', 'due_at' => '2026-06-01T10:00:00Z'],
    ], [], $this->now, 7);

    expect($items)->toHaveCount(1)
        ->and($items[0]['id'])->toBe('c1')
        ->and($items[0]['kind'])->toBe('activity');
});

it('never lists achieved or abandoned objectives', function () {
    $items = $this->calc->build([], [], [
        ['id' => 'o1', 'status' => 'in_progress', 'due_at' => '2026-06-18T10:00:00Z'],
        ['id' => 'o2', 'status' => 'achieved', 'due_at' => '2026-06-18T10:00:00Z'],
        ['id' => 'o3', 'status' => 'abandoned', 'due_at' => '2026-06-18T10:00:00Z'],
    ], $this->now, 7);

    expect($items)->toHaveCount(1)
        ->and($items[0]['id'])->toBe('o1');
});

it('lists future scheduled sessions and sorts everything by date', function () {
    $items = $this->calc->build([
        ['id' => 's1', 'title' => 'Far session', 'status' => 'scheduled', 'scheduled_at' => '2026-08-01T10:00:00Z'],
    ], [
        ['id' => 'c1', 'title' => 'Near activity', 'due_at' => '2026-06-16T10:00:00Z'],
    ], [], $this->now, 7);

    expect($items)->toHaveCount(2)
        ->and($items[0]['kind'])->toBe('activity')
        ->and($items[1]['kind'])->toBe('session');
});

it('respects the exact window boundary and the subject timezone', function () {
    $items = $this->calc->build([], [
        ['id' => 'boundary', 'due_at' => '2026-06-22T12:00:00Z'],
        ['id' => 'just-after', 'due_at' => '2026-06-22T12:00:01Z'],
    ], [], $this->now, 7);

    expect(collect($items)->pluck('id')->all())->toBe(['boundary']);
});
