<?php

namespace App\Services\Progress\Calculators;

use App\Services\Progress\Support\MetricValue;
use App\Services\Progress\Support\PeriodResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * F5–F6 — pure session metrics (RF-5, RF-14).
 *
 * A session counts as "realized" only when it is `completed` AND the subject
 * has a confirmed `attended=true` attendance, within the current calendar month
 * in the subject timezone (spec §3.1.3/§3.1.4).
 */
final class SessionMetricsCalculator
{
    /**
     * F5 — realized sessions within the calendar month.
     *
     * @param  array<int, array<string, mixed>>  $sessions
     * @param  array<string, array<string, array{attended?: bool}>>  $attendeeMap  [sessionId][userId => ['attended' => bool]]
     */
    public function realized(
        array $sessions,
        array $attendeeMap,
        string $userId,
        CarbonInterface $now,
        string $timezone = 'UTC',
    ): MetricValue {
        $range = PeriodResolver::currentMonthRange($now, $timezone);
        $period = PeriodResolver::resolve(PeriodResolver::CURRENT_MONTH, $now, $timezone);

        $inMonth = 0;
        $realized = 0;

        foreach ($sessions as $session) {
            $scheduledAt = $session['scheduled_at'] ?? null;

            if ($scheduledAt === null || $scheduledAt === '') {
                continue;
            }

            $local = CarbonImmutable::parse($scheduledAt)->setTimezone($timezone);

            if ($local->lt($range['from']) || $local->gt($range['to'])) {
                continue;
            }

            $inMonth++;

            if (($session['status'] ?? null) !== 'completed') {
                continue;
            }

            $attendance = $attendeeMap[$session['id'] ?? ''][$userId] ?? null;

            if (($attendance['attended'] ?? false) !== true) {
                continue;
            }

            $realized++;
        }

        return MetricValue::make($inMonth, $realized, [
            'source' => 'sessions+attendance',
            'period' => $period,
        ]);
    }

    /**
     * F6 — scheduled sessions with a future date, sorted ascending.
     *
     * @param  array<int, array<string, mixed>>  $sessions
     * @return array<int, array<string, mixed>>
     */
    public function scheduled(array $sessions, CarbonInterface $now): array
    {
        $now = CarbonImmutable::instance($now);
        $items = [];

        foreach ($sessions as $session) {
            if (($session['status'] ?? null) !== 'scheduled') {
                continue;
            }

            $scheduledAt = $session['scheduled_at'] ?? null;

            if ($scheduledAt === null || $scheduledAt === '') {
                continue;
            }

            $at = CarbonImmutable::parse($scheduledAt);

            if ($at->lte($now)) {
                continue;
            }

            $items[] = [
                'id' => $session['id'] ?? null,
                'title' => $session['title'] ?? null,
                'at' => $at->toIso8601String(),
            ];
        }

        usort($items, fn (array $a, array $b): int => strcmp($a['at'], $b['at']));

        return $items;
    }
}
