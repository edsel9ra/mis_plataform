<?php

namespace App\Services\Progress\Calculators;

use App\Enums\ObjectiveStatus;
use App\Services\Progress\Support\MetricValue;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * F1–F4 — pure objective metrics (RF-4, RF-13, RF-14).
 *
 * Operates on plain arrays already loaded from the database. No IO, no clock.
 */
final class ObjectiveMetricsCalculator
{
    /**
     * F1 — achievement percentage: achieved / total (null when there are no
     * objectives). `abandoned` stays in the denominator (Anexo A).
     *
     * @param  array<int, array<string, mixed>>  $objectives
     */
    public function achievement(array $objectives, ?array $period = null): MetricValue
    {
        $total = count($objectives);
        $achieved = $this->countByStatus($objectives, ObjectiveStatus::Achieved->value);

        return MetricValue::make($total, MetricValue::percent($achieved, $total), [
            'numerator' => $achieved,
            'denominator' => $total,
            'source' => 'objectives',
            'period' => $period,
        ]);
    }

    /**
     * F2 — counts per status (all four statuses, including abandoned).
     *
     * @param  array<int, array<string, mixed>>  $objectives
     * @return array<string, int>
     */
    public function byStatus(array $objectives): array
    {
        $counts = [];

        foreach (ObjectiveStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        foreach ($objectives as $objective) {
            $status = $this->statusOf($objective);

            if ($status !== null && array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
        }

        return $counts;
    }

    /**
     * F3 — an objective is overdue when it has a due date in the past and has
     * not been achieved. `achieved` is never overdue (CA-2.4).
     *
     * @param  array<string, mixed>  $objective
     */
    public function isOverdue(array $objective, CarbonInterface $now): bool
    {
        if ($this->statusOf($objective) === ObjectiveStatus::Achieved->value) {
            return false;
        }

        $dueAt = $objective['due_at'] ?? null;

        if ($dueAt === null || $dueAt === '') {
            return false;
        }

        return CarbonImmutable::parse($dueAt)->lt(CarbonImmutable::instance($now));
    }

    /**
     * F4 — classification for the obligatory RF-3 block.
     *
     * @param  array<int, array<string, mixed>>  $objectives
     * @return array{pending: int, overdue: int, achieved: int, abandoned: int}
     */
    public function classify(array $objectives, CarbonInterface $now): array
    {
        $result = ['pending' => 0, 'overdue' => 0, 'achieved' => 0, 'abandoned' => 0];

        foreach ($objectives as $objective) {
            $status = $this->statusOf($objective);

            if ($status === ObjectiveStatus::Achieved->value) {
                $result['achieved']++;
                continue;
            }

            if ($status === ObjectiveStatus::Abandoned->value) {
                $result['abandoned']++;
                continue;
            }

            $result['pending']++;

            if ($this->isOverdue($objective, $now)) {
                $result['overdue']++;
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $objectives
     */
    private function countByStatus(array $objectives, string $status): int
    {
        return count(array_filter(
            $objectives,
            fn (array $objective): bool => $this->statusOf($objective) === $status,
        ));
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
