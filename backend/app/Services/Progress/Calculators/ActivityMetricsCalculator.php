<?php

namespace App\Services\Progress\Calculators;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * F7 — pure activity metrics (RF-6).
 *
 * Pending = challenges `assigned`/`in_progress` + unfinished learning modules.
 * `canceled`/`template` are excluded from both pending and completed (CA-6.5).
 * An activity without a due date is never overdue (CA-6.3).
 */
final class ActivityMetricsCalculator
{
    private const PENDING_CHALLENGE_STATUSES = ['assigned', 'in_progress'];

    private const EXCLUDED_CHALLENGE_STATUSES = ['canceled', 'template'];

    /**
     * @param  array<int, array<string, mixed>>  $challenges
     * @param  array<int, array<string, mixed>>  $pathProgress
     * @return array{pending: array<int, array<string, mixed>>, pending_count: int, overdue: int, completed: int, total_records: int}
     */
    public function classify(array $challenges, array $pathProgress, CarbonInterface $now): array
    {
        $now = CarbonImmutable::instance($now);
        $pending = [];
        $overdue = 0;
        $completed = 0;
        $records = 0;

        foreach ($challenges as $challenge) {
            $status = $challenge['status'] ?? null;

            if (in_array($status, self::EXCLUDED_CHALLENGE_STATUSES, true)) {
                continue;
            }

            $records++;

            if ($status === 'completed') {
                $completed++;
                continue;
            }

            if (! in_array($status, self::PENDING_CHALLENGE_STATUSES, true)) {
                continue;
            }

            $item = $this->describe('challenge', $challenge, $now);

            if ($item['overdue']) {
                $overdue++;
            }

            $pending[] = $item;
        }

        foreach ($pathProgress as $progress) {
            $records++;

            if (($progress['status'] ?? null) === 'completed') {
                $completed++;
                continue;
            }

            $pending[] = [
                'type' => 'learning_module',
                'id' => $progress['id'] ?? null,
                'title' => $progress['title'] ?? null,
                'due_at' => null,
                'overdue' => false,
            ];
        }

        return [
            'pending' => $pending,
            'pending_count' => count($pending),
            'overdue' => $overdue,
            'completed' => $completed,
            'total_records' => $records,
        ];
    }

    /**
     * @param  array<string, mixed>  $challenge
     * @return array<string, mixed>
     */
    private function describe(string $type, array $challenge, CarbonInterface $now): array
    {
        $dueAt = $challenge['due_at'] ?? null;
        $overdue = false;

        if ($dueAt !== null && $dueAt !== '') {
            $overdue = CarbonImmutable::parse($dueAt)->lt(CarbonImmutable::instance($now));
        }

        return [
            'type' => $type,
            'id' => $challenge['id'] ?? null,
            'title' => $challenge['title'] ?? null,
            'due_at' => $dueAt,
            'overdue' => $overdue,
        ];
    }
}
