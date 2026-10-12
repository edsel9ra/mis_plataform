<?php

use App\Services\Progress\Support\MetricValue;

uses()->group('progress');

it('distinguishes no_data from a real zero', function () {
    $noData = MetricValue::make(0, null);
    $zero = MetricValue::make(3, 0);
    $value = MetricValue::make(3, 2);

    expect($noData->state)->toBe(MetricValue::NO_DATA)
        ->and($noData->value)->toBeNull()
        ->and($zero->state)->toBe(MetricValue::ZERO)
        ->and($zero->value)->toBe(0)
        ->and($value->state)->toBe(MetricValue::VALUE)
        ->and($value->value)->toBe(2);
});

it('marks incomplete data and carries the full contract', function () {
    $metric = MetricValue::make(2, 1, [
        'numerator' => 1,
        'denominator' => 2,
        'source' => 'objectives',
        'period' => ['type' => 'current_state'],
    ]);
    $array = $metric->toArray();

    expect($array['numerator'])->toBe(1)
        ->and($array['denominator'])->toBe(2)
        ->and($array['source'])->toBe('objectives')
        ->and($array['period'])->toBe(['type' => 'current_state'])
        ->and($array['incomplete'])->toBeFalse();

    $incomplete = MetricValue::make(2, 1, ['incomplete' => true]);

    expect($incomplete->state)->toBe(MetricValue::INCOMPLETE)
        ->and($incomplete->incomplete)->toBeTrue();
});

it('returns a null percent when the denominator is zero', function () {
    expect(MetricValue::percent(0, 0))->toBeNull()
        ->and(MetricValue::percent(1, 2))->toBe(50)
        ->and(MetricValue::percent(2, 3))->toBe(67);
});
