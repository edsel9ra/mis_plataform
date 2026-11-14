<?php

use App\Services\Progress\Calculators\ChallengeModuleCalculator;
use App\Services\Progress\Support\MetricValue;

uses()->group('progress', 'calculators');

function challenges(array $statuses): array
{
    return array_map(fn ($status) => ['status' => $status], $statuses);
}

describe('activityRate', function (): void {
    it('returns incomplete without numerator when a non-excluded challenge has an unknown status', function (mixed $unknown): void {
        $result = (new ChallengeModuleCalculator)->activityRate(challenges(['completed', $unknown]));

        expect($result->state)->toBe(MetricValue::INCOMPLETE)
            ->and($result->value)->toBeNull()
            ->and($result->numerator)->toBeNull()
            ->and($result->denominator)->toBeNull()
            ->and($result->incomplete)->toBeTrue()
            ->and($result->source)->toBe('challenge_status');
    })->with([
        'null' => [null],
        'empty string' => [''],
        'unknown string' => ['paused'],
    ]);

    it('returns no_data when there are no eligible challenges', function (array $statuses): void {
        $result = (new ChallengeModuleCalculator)->activityRate(challenges($statuses));

        expect($result->state)->toBe(MetricValue::NO_DATA)
            ->and($result->value)->toBeNull();
    })->with([
        'empty' => [[]],
        'all excluded' => [['template', 'canceled', 'abandoned']],
    ]);

    it('excludes template, canceled and abandoned without blocking the rate', function (): void {
        $result = (new ChallengeModuleCalculator)->activityRate(
            challenges(['completed', 'abandoned', 'canceled', 'template'])
        );

        expect($result->state)->toBe(MetricValue::VALUE)
            ->and($result->value)->toBe(100)
            ->and($result->numerator)->toBe(1)
            ->and($result->denominator)->toBe(1);
    });

    it('computes completed/eligible*100 with reconciled numerator and denominator', function (): void {
        $result = (new ChallengeModuleCalculator)->activityRate(
            challenges(['completed', 'completed', 'in_progress', 'assigned'])
        );

        expect($result->state)->toBe(MetricValue::VALUE)
            ->and($result->value)->toBe(50)
            ->and($result->numerator)->toBe(2)
            ->and($result->denominator)->toBe(4);
    });

    it('distinguishes a real 0% from no_data', function (): void {
        $result = (new ChallengeModuleCalculator)->activityRate(challenges(['assigned', 'in_progress']));

        expect($result->state)->toBe(MetricValue::ZERO)
            ->and($result->value)->toBe(0)
            ->and($result->numerator)->toBe(0)
            ->and($result->denominator)->toBe(2);
    });

    it('rounds like the sibling calculators', function (): void {
        $result = (new ChallengeModuleCalculator)->activityRate(
            challenges(['completed', 'assigned', 'in_progress'])
        );

        expect($result->value)->toBe(33); // 1/3 → int round
    });
});

describe('moduleCompletion', function (): void {
    it('returns incomplete when any non-excluded challenge has an unknown status', function (): void {
        $result = (new ChallengeModuleCalculator)->moduleCompletion(
            challenges(['completed', null, 'canceled'])
        );

        expect($result)->toBe(ChallengeModuleCalculator::MODULE_INCOMPLETE);
    });

    it('returns no_data when there is no eligible challenge', function (): void {
        expect((new ChallengeModuleCalculator)->moduleCompletion([]))
            ->toBe(ChallengeModuleCalculator::MODULE_NO_DATA)
            ->and((new ChallengeModuleCalculator)->moduleCompletion(challenges(['abandoned'])))
            ->toBe(ChallengeModuleCalculator::MODULE_NO_DATA);
    });

    it('returns completed only when every eligible challenge is completed', function (): void {
        $calc = new ChallengeModuleCalculator;

        expect($calc->moduleCompletion(challenges(['completed', 'completed', 'abandoned'])))
            ->toBe(ChallengeModuleCalculator::MODULE_COMPLETED)
            ->and($calc->moduleCompletion(challenges(['completed', 'in_progress'])))
            ->toBe(ChallengeModuleCalculator::MODULE_NOT_COMPLETED)
            ->and($calc->moduleCompletion(challenges(['assigned'])))
            ->toBe(ChallengeModuleCalculator::MODULE_NOT_COMPLETED);
    });
});
