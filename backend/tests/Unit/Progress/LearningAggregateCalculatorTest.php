<?php

use App\Services\Progress\Calculators\LearningAggregateCalculator;
use App\Services\Progress\Map\AggregateMetric;
use App\Services\Progress\Map\CollectiveLearningAggregate;
use App\Services\Progress\Support\MetricValue;
use App\Services\Progress\Support\SuppressionReason;

/**
 * Pure collective learning aggregate (RF-013.6–.8): means of comparable
 * individual percentages only, general base threshold separate from the
 * per-metric contribution threshold, missing metrics never counted as zero.
 */

function learningContribution(string $state, int|float|null $value = null): MetricValue
{
    return new MetricValue(state: $state, value: $value);
}

function pathGroup(array $contributions, string $pathId = 'path-1', string $versionKey = 'v1', string $title = 'Ruta'): array
{
    return [
        'path_id' => $pathId,
        'version_key' => $versionKey,
        'title' => $title,
        'contributions' => $contributions,
    ];
}

it('averages calculable contributions into the metric value', function () {
    $calculator = new LearningAggregateCalculator;

    $metric = $calculator->meanComparable([
        MetricValue::value(50),
        MetricValue::value(60),
        MetricValue::value(70),
    ]);

    expect($metric->state)->toBe(AggregateMetric::STATE_VALUE);
    expect($metric->value)->toBe(60);
    expect($metric->suppressionReason)->toBeNull();
});

it('rounds the mean like the rest of the progress calculators', function () {
    $calculator = new LearningAggregateCalculator;

    $metric = $calculator->meanComparable([
        MetricValue::value(10),
        MetricValue::value(20),
        MetricValue::value(20),
    ]);

    expect($metric->value)->toBe(17); // 50/3 = 16.67
});

it('counts a real zero as a calculable contribution', function () {
    $calculator = new LearningAggregateCalculator;

    $metric = $calculator->meanComparable([
        MetricValue::value(0), // state 'zero'
        MetricValue::value(50),
        MetricValue::value(100),
    ]);

    expect($metric->state)->toBe(AggregateMetric::STATE_VALUE);
    expect($metric->value)->toBe(50);
});

it('suppresses the metric when calculable contributions fall below the threshold', function () {
    $calculator = new LearningAggregateCalculator;

    $metric = $calculator->meanComparable([
        MetricValue::value(50),
        MetricValue::value(70),
    ]);

    expect($metric->state)->toBe(AggregateMetric::STATE_SUPPRESSED);
    expect($metric->value)->toBeNull();
    expect($metric->suppressionReason)->toBe(SuppressionReason::InsufficientMetricContributions);
});

it('never turns missing metrics into zero contributions', function () {
    $calculator = new LearningAggregateCalculator;

    // Two calculable + three missing: suppressed (2 < 3), not a mean that
    // treats the absent ones as 0 (which would falsely yield 24).
    $metric = $calculator->meanComparable([
        MetricValue::value(50),
        MetricValue::value(70),
        MetricValue::noData(),
        MetricValue::incomplete(50),
        learningContribution(MetricValue::SUPPRESSED),
        learningContribution(MetricValue::BLOCKED),
    ]);

    expect($metric->state)->toBe(AggregateMetric::STATE_SUPPRESSED);
    expect($metric->suppressionReason)->toBe(SuppressionReason::InsufficientMetricContributions);
});

it('applies the same shared threshold of 3 as the baseline aggregate', function () {
    $calculator = new LearningAggregateCalculator;

    $two = $calculator->meanComparable([MetricValue::value(10), MetricValue::value(20)]);
    $three = $calculator->meanComparable([MetricValue::value(10), MetricValue::value(20), MetricValue::value(30)]);

    expect($two->state)->toBe(AggregateMetric::STATE_SUPPRESSED);
    expect($three->state)->toBe(AggregateMetric::STATE_VALUE);
    expect($three->value)->toBe(20);
});

it('suppresses the whole block when the eligible base is below the threshold', function () {
    $calculator = new LearningAggregateCalculator;

    $aggregate = $calculator->collective(
        eligibleCount: 2,
        activityContributions: [MetricValue::value(50), MetricValue::value(60), MetricValue::value(70)],
        pathGroups: [pathGroup([MetricValue::value(80), MetricValue::value(90), MetricValue::value(100)])],
    );

    expect($aggregate->state)->toBe(CollectiveLearningAggregate::STATE_SUPPRESSED);
    expect($aggregate->suppressionReason)->toBe(SuppressionReason::InsufficientBase);
    expect($aggregate->activityRate)->toBeNull();
    expect($aggregate->rates)->toBe([]);
});

it('computes every metric independently once the base reaches the threshold', function () {
    $calculator = new LearningAggregateCalculator;

    $aggregate = $calculator->collective(
        eligibleCount: 3,
        activityContributions: [MetricValue::value(50), MetricValue::value(70), MetricValue::noData()],
        pathGroups: [
            pathGroup(
                [MetricValue::value(60), MetricValue::value(80), MetricValue::value(100)],
                pathId: 'path-1', versionKey: 'v1', title: 'Ruta Uno',
            ),
            pathGroup(
                [MetricValue::value(30), MetricValue::noData(), MetricValue::incomplete(90)],
                pathId: 'path-2', versionKey: 'v2', title: 'Ruta Dos',
            ),
        ],
    );

    expect($aggregate->state)->toBe(CollectiveLearningAggregate::STATE_AVAILABLE);
    expect($aggregate->suppressionReason)->toBeNull();

    // Activity rate: 2 calculable → suppressed.
    expect($aggregate->activityRate->state)->toBe(AggregateMetric::STATE_SUPPRESSED);
    expect($aggregate->activityRate->suppressionReason)->toBe(SuppressionReason::InsufficientMetricContributions);

    // path-1 has 3 calculable → value; path-2 has 1 → suppressed. One
    // suppressed metric never suppresses its siblings.
    expect($aggregate->rates)->toHaveCount(2);
    expect($aggregate->rates[0]->pathId)->toBe('path-1');
    expect($aggregate->rates[0]->versionKey)->toBe('v1');
    expect($aggregate->rates[0]->title)->toBe('Ruta Uno');
    expect($aggregate->rates[0]->metric->state)->toBe(AggregateMetric::STATE_VALUE);
    expect($aggregate->rates[0]->metric->value)->toBe(80);

    expect($aggregate->rates[1]->pathId)->toBe('path-2');
    expect($aggregate->rates[1]->metric->state)->toBe(AggregateMetric::STATE_SUPPRESSED);
    expect($aggregate->rates[1]->metric->suppressionReason)->toBe(SuppressionReason::InsufficientMetricContributions);
});

it('treats an empty collective as an available block with suppressed metrics, not as suppressed base', function () {
    $calculator = new LearningAggregateCalculator;

    $aggregate = $calculator->collective(
        eligibleCount: 3,
        activityContributions: [],
        pathGroups: [],
    );

    expect($aggregate->state)->toBe(CollectiveLearningAggregate::STATE_AVAILABLE);
    expect($aggregate->activityRate->state)->toBe(AggregateMetric::STATE_SUPPRESSED);
    expect($aggregate->rates)->toBe([]);
});
