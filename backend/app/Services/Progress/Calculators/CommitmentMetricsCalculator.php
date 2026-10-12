<?php

namespace App\Services\Progress\Calculators;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * F9 — pure upcoming-commitments metrics (RF-8).
 *
 * Future `scheduled` sessions (no window cap, CA-8.1) plus activity and
 * objective deadlines within the 7-day window (CA-8.2). Achieved/abandoned
 * objectives are excluded. Sorted ascending by date.
 */
final class CommitmentMetricsCalculator
{
    /**
     * @param  array<int, array<string, mixed>>  $sessions
     * @param  array<int, array<string, mixed>>  $challenges
     * @param  array<int, array<string, mixed>>  $objectives
     * @return array<int, array<string, mixed>>
     */
    public function build(
        array $sessions,
        array $challenges,
        array $objectives,
        CarbonInterface $now,
        int $windowDays = 7,
        string $timezone = 'UTC',
    ): array {
        $now = CarbonImmutable::instance($now)->setTimezone($timezone);
        $windowEnd = $now->addDays($windowDays);
        $items = [];

        foreach ($sessions as $session) {
            if (($session['status'] ?? null) !== 'scheduled') {
                continue;
            }

            $at = $this->toLocal($session['scheduled_at'] ?? null, $timezone);

            if ($at === null || $at->lte($now)) {
                continue;
            }

            $items[] = [
                'kind' => 'session',
                'id' => $session['id'] ?? null,
                'title' => $session['title'] ?? null,
                'at' => $at->toIso8601String(),
            ];
        }

        foreach ($challenges as $challenge) {
            $at = $this->toLocal($challenge['due_at'] ?? null, $timezone);

            if ($at === null || $at->lte($now) || $at->gt($windowEnd)) {
                continue;
            }

            $items[] = [
                'kind' => 'activity',
                'id' => $challenge['id'] ?? null,
                'title' => $challenge['title'] ?? null,
                'at' => $at->toIso8601String(),
            ];
        }

        foreach ($objectives as $objective) {
            $status = $objective['status'] ?? null;
            $status = $status instanceof \App\Enums\ObjectiveStatus ? $status->value : $status;

            if (in_array($status, ['achieved', 'abandoned'], true)) {
                continue;
            }

            $at = $this->toLocal($objective['due_at'] ?? null, $timezone);

            if ($at === null || $at->lte($now) || $at->gt($windowEnd)) {
                continue;
            }

            $items[] = [
                'kind' => 'objective',
                'id' => $objective['id'] ?? null,
                'title' => $objective['title'] ?? null,
                'at' => $at->toIso8601String(),
            ];
        }

        usort($items, fn (array $a, array $b): int => strcmp($a['at'], $b['at']));

        return $items;
    }

    private function toLocal(mixed $value, string $timezone): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->setTimezone($timezone);
    }
}
