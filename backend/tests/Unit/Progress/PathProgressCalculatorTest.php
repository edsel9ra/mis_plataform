<?php

use App\Services\Progress\Calculators\ChallengeModuleCalculator;
use App\Services\Progress\Calculators\PathProgressCalculator;
use App\Services\Progress\Support\MetricValue;

uses()->group('progress', 'calculators');

function modules(array $entries): array
{
    return array_map(
        fn (array $entry) => [
            'completion' => $entry[0],
            'completed_record' => $entry[1] ?? false,
        ],
        $entries,
    );
}

it('returns incomplete without a partial rate when any module is incomplete', function (): void {
    $result = (new PathProgressCalculator)->pathRate(modules([
        [ChallengeModuleCalculator::MODULE_COMPLETED, true],
        [ChallengeModuleCalculator::MODULE_INCOMPLETE],
    ]));

    expect($result->state)->toBe(MetricValue::INCOMPLETE)
        ->and($result->value)->toBeNull()
        ->and($result->numerator)->toBeNull()
        ->and($result->incomplete)->toBeTrue();
});

it('returns no_data when there are no eligible modules', function (): void {
    $calc = new PathProgressCalculator;

    expect($calc->pathRate([])->state)->toBe(MetricValue::NO_DATA)
        ->and($calc->pathRate([])->value)->toBeNull()
        ->and($calc->pathRate(modules([
            [ChallengeModuleCalculator::MODULE_NO_DATA],
            [ChallengeModuleCalculator::MODULE_NO_DATA],
        ]))->state)->toBe(MetricValue::NO_DATA);
});

it('counts only eligible modules with a persisted completion record in the numerator', function (): void {
    $result = (new PathProgressCalculator)->pathRate(modules([
        [ChallengeModuleCalculator::MODULE_COMPLETED, true],
        [ChallengeModuleCalculator::MODULE_COMPLETED, false], // rule done, record missing
        [ChallengeModuleCalculator::MODULE_NOT_COMPLETED],
        [ChallengeModuleCalculator::MODULE_NO_DATA], // not eligible, ignored
    ]));

    expect($result->state)->toBe(MetricValue::VALUE)
        ->and($result->numerator)->toBe(1)
        ->and($result->denominator)->toBe(3)
        ->and($result->value)->toBe(33);
});

it('distinguishes a real 0% from no_data', function (): void {
    $result = (new PathProgressCalculator)->pathRate(modules([
        [ChallengeModuleCalculator::MODULE_NOT_COMPLETED],
        [ChallengeModuleCalculator::MODULE_NOT_COMPLETED],
    ]));

    expect($result->state)->toBe(MetricValue::ZERO)
        ->and($result->value)->toBe(0)
        ->and($result->numerator)->toBe(0)
        ->and($result->denominator)->toBe(2);
});

it('reaches 100 only when every eligible module has a completed record', function (): void {
    $result = (new PathProgressCalculator)->pathRate(modules([
        [ChallengeModuleCalculator::MODULE_COMPLETED, true],
        [ChallengeModuleCalculator::MODULE_COMPLETED, true],
    ]));

    expect($result->value)->toBe(100)
        ->and($result->numerator)->toBe(2)
        ->and($result->denominator)->toBe(2);
});
