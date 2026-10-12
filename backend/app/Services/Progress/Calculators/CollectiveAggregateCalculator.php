<?php

namespace App\Services\Progress\Calculators;

use App\Enums\ObjectiveStatus;
use App\Services\Progress\Support\MetricValue;

/**
 * F10–F11 — pure collective aggregate with a uniform privacy threshold
 * (RF-10, RF-11, RNF-2).
 *
 * Suppression is uniform for managers and members alike and never reveals the
 * exact size of the base. A collective with no contributing members is
 * `no_data`, never a misleading mean (CA-10.3).
 */
final class CollectiveAggregateCalculator
{
    public const DEFAULT_THRESHOLD = 3;
    public const SUPPRESSION_REASON = 'insufficient_base';

    /**
     * F11 — base below the threshold must be suppressed.
     */
    public function shouldSuppressAggregate(int $count, ?int $threshold = null): bool
    {
        $threshold ??= self::DEFAULT_THRESHOLD;

        return $count < $threshold;
    }

    /**
     * F10 — sum of achieved/total plus the mean of per-member percentages.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $objectivesByMember
     * @return array{
     *     state: string,
     *     suppressed: bool,
     *     reason: string|null,
     *     mean: int|null,
     *     sum: array{achieved: int, total: int},
     *     contributing_members: int,
     *     eligible_members: int
     * }
     */
    public function aggregate(array $objectivesByMember, int $eligibleMembers, ?int $threshold = null): array
    {
        $threshold ??= self::DEFAULT_THRESHOLD;

        $contributing = 0;
        $sumAchieved = 0;
        $sumTotal = 0;
        $percentages = [];

        foreach ($objectivesByMember as $objectives) {
            $total = count($objectives);

            if ($total === 0) {
                continue;
            }

            $achieved = count(array_filter(
                $objectives,
                fn (array $objective): bool => $this->statusOf($objective) === ObjectiveStatus::Achieved->value,
            ));

            $contributing++;
            $sumAchieved += $achieved;
            $sumTotal += $total;

            $percentage = MetricValue::percent($achieved, $total);

            if ($percentage !== null) {
                $percentages[] = $percentage;
            }
        }

        if ($contributing === 0) {
            return $this->result('no_data', false, null, $eligibleMembers, 0, $sumAchieved, $sumTotal);
        }

        $suppressed = $this->shouldSuppressAggregate($eligibleMembers, $threshold)
            || $this->shouldSuppressAggregate($contributing, $threshold);

        if ($suppressed) {
            return $this->result(
                MetricValue::SUPPRESSED,
                true,
                self::SUPPRESSION_REASON,
                $eligibleMembers,
                $contributing,
                $sumAchieved,
                $sumTotal,
            );
        }

        $mean = $percentages !== []
            ? (int) round(array_sum($percentages) / count($percentages))
            : null;

        return $this->result('value', false, null, $eligibleMembers, $contributing, $sumAchieved, $sumTotal, $mean);
    }

    /**
     * @return array{
     *     state: string,
     *     suppressed: bool,
     *     reason: string|null,
     *     mean: int|null,
     *     sum: array{achieved: int, total: int},
     *     contributing_members: int,
     *     eligible_members: int
     * }
     */
    private function result(
        string $state,
        bool $suppressed,
        ?string $reason,
        int $eligibleMembers,
        int $contributing,
        int $sumAchieved,
        int $sumTotal,
        ?int $mean = null,
    ): array {
        return [
            'state' => $state,
            'suppressed' => $suppressed,
            'reason' => $reason,
            'mean' => $mean,
            'sum' => ['achieved' => $sumAchieved, 'total' => $sumTotal],
            'contributing_members' => $contributing,
            'eligible_members' => $eligibleMembers,
        ];
    }

    /**
     * @param  array<string, mixed>  $objective
     */
    private function statusOf(array $objective): ?string
    {
        $status = $objective['status'] ?? null;

        return $status instanceof ObjectiveStatus ? $status->value : (is_string($status) ? $status : null);
    }
}
