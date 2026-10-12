<?php

use App\Services\Progress\Calculators\CollectiveAggregateCalculator;
use App\Services\Progress\Support\MetricValue;

uses()->group('progress');

beforeEach(function () {
    $this->calc = new CollectiveAggregateCalculator;
});

it('shows the aggregate when the base is at the threshold', function () {
    $aggregate = $this->calc->aggregate([
        'm1' => [['status' => 'achieved'], ['status' => 'in_progress']],
        'm2' => [['status' => 'achieved'], ['status' => 'achieved']],
        'm3' => [['status' => 'not_started']],
    ], 3, 3);

    expect($aggregate['state'])->toBe('value')
        ->and($aggregate['suppressed'])->toBeFalse()
        ->and($aggregate['sum'])->toBe(['achieved' => 3, 'total' => 5])
        ->and($aggregate['contributing_members'])->toBe(3)
        ->and($aggregate['mean'])->toBe(50);
});

it('suppresses the aggregate below the threshold', function () {
    $aggregate = $this->calc->aggregate([
        'm1' => [['status' => 'achieved']],
        'm2' => [['status' => 'in_progress']],
    ], 2, 3);

    expect($aggregate['state'])->toBe(MetricValue::SUPPRESSED)
        ->and($aggregate['suppressed'])->toBeTrue()
        ->and($aggregate['reason'])->toBe('insufficient_base');
});

it('returns no_data when no member has objectives', function () {
    $aggregate = $this->calc->aggregate([
        'm1' => [],
        'm2' => [],
    ], 2, 3);

    expect($aggregate['state'])->toBe('no_data')
        ->and($aggregate['suppressed'])->toBeFalse()
        ->and($aggregate['mean'])->toBeNull();
});

it('exposes a reusable suppression rule', function () {
    expect($this->calc->shouldSuppressAggregate(2, 3))->toBeTrue()
        ->and($this->calc->shouldSuppressAggregate(3, 3))->toBeFalse()
        ->and($this->calc->shouldSuppressAggregate(3))->toBeFalse();
});
