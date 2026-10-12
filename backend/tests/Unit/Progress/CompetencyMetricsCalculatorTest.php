<?php

use App\Services\Progress\Calculators\CompetencyMetricsCalculator;

uses()->group('progress');

beforeEach(function () {
    $this->calc = new CompetencyMetricsCalculator;
});

it('returns no_data when there are no skills and no completed assessment', function () {
    $result = $this->calc->build([], null, true);

    expect($result['state'])->toBe('no_data')
        ->and($result['skills'])->toBe([])
        ->and($result['ocean']['state'])->toBe('no_data');
});

it('blocks the OCEAN section when the entitlement is missing', function () {
    $result = $this->calc->build([], null, false);

    expect($result['state'])->toBe('blocked')
        ->and($result['ocean']['state'])->toBe('blocked')
        ->and($result['ocean']['reason'])->toBe('entitlement_missing');
});

it('keeps skills and personality as distinguishable sources', function () {
    $result = $this->calc->build(
        [['id' => 'sk1', 'tag' => 'Empathy', 'category' => 'social', 'level' => 4]],
        [
            'factors' => ['O' => 70, 'C' => 65, 'E' => 60, 'A' => 80, 'N' => 30],
            'completed_at' => '2026-05-01T00:00:00Z',
            'test_version' => 'ipip-neo-120',
        ],
        true,
    );

    expect($result['state'])->toBe('value')
        ->and($result['skills'][0]['source'])->toBe('skills')
        ->and($result['skills'][0]['tag'])->toBe('Empathy')
        ->and($result['ocean']['state'])->toBe('value')
        ->and($result['ocean']['source'])->toBe('personality')
        ->and($result['ocean']['assessed_at'])->toBe('2026-05-01T00:00:00Z');
});
