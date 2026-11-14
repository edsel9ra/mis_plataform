<?php

namespace App\Services\Progress\Calculators;

use App\Services\Progress\Map\AggregateMetric;
use App\Services\Progress\Map\CollectiveLearningAggregate;
use App\Services\Progress\Map\PathAggregateRate;
use App\Services\Progress\Support\MetricValue;
use App\Services\Progress\Support\SuppressionReason;

/**
 * Collective learning rates: arithmetic mean of comparable individual
 * percentages with two independent privacy gates (RF-013.6–.8).
 *
 * Pure: operates on MetricValue instances already resolved by the caller,
 * no DB, no clock. A contribution is calculable only when the individual
 * percentage is defined (`value`/`zero` with a recorded value) — a real 0%
 * counts, while `no_data`/`incomplete` or any other state does not
 * contribute and is never treated as zero. The eligible-base gate reuses
 * the baseline threshold; each metric is then suppressed independently.
 */
final class LearningAggregateCalculator
{
    /**
     * Mean of the calculable contributions for one metric.
     *
     * @param  array<int, MetricValue>  $contributions  same metric, same
     *                                                 path/version — the caller groups comparables
     */
    public function meanComparable(array $contributions, ?int $threshold = null): AggregateMetric
    {
        $threshold ??= CollectiveAggregateCalculator::DEFAULT_THRESHOLD;

        $values = [];

        foreach ($contributions as $metric) {
            if (! in_array($metric->state, [MetricValue::VALUE, MetricValue::ZERO], true)) {
                continue;
            }

            if ($metric->value === null) {
                continue;
            }

            $values[] = $metric->value;
        }

        if (count($values) < $threshold) {
            return new AggregateMetric(
                state: AggregateMetric::STATE_SUPPRESSED,
                value: null,
                suppressionReason: SuppressionReason::InsufficientMetricContributions,
            );
        }

        return new AggregateMetric(
            state: AggregateMetric::STATE_VALUE,
            value: (int) round(array_sum($values) / count($values)),
            suppressionReason: null,
        );
    }

    /**
     * Whole collective learning block: an eligible base below the shared
     * threshold suppresses everything; otherwise each metric is computed
     * independently and a suppressed metric never affects its siblings.
     *
     * @param  array<int, MetricValue>  $activityContributions
     * @param  array<int, array{path_id: string, version_key: string, title: string, contributions: array<int, MetricValue>}>  $pathGroups
     */
    public function collective(
        int $eligibleCount,
        array $activityContributions,
        array $pathGroups,
        ?int $threshold = null,
    ): CollectiveLearningAggregate {
        $threshold ??= CollectiveAggregateCalculator::DEFAULT_THRESHOLD;

        if ($eligibleCount < $threshold) {
            return new CollectiveLearningAggregate(
                state: CollectiveLearningAggregate::STATE_SUPPRESSED,
                suppressionReason: SuppressionReason::InsufficientBase,
                activityRate: null,
                rates: [],
            );
        }

        return new CollectiveLearningAggregate(
            state: CollectiveLearningAggregate::STATE_AVAILABLE,
            suppressionReason: null,
            activityRate: $this->meanComparable($activityContributions, $threshold),
            rates: array_map(
                fn (array $group): PathAggregateRate => new PathAggregateRate(
                    pathId: $group['path_id'],
                    versionKey: $group['version_key'],
                    title: $group['title'],
                    metric: $this->meanComparable($group['contributions'], $threshold),
                ),
                $pathGroups,
            ),
        );
    }
}
