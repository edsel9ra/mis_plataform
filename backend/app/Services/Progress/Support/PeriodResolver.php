<?php

namespace App\Services\Progress\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * F13 — resolve the period that matches each indicator's nature (RF-14).
 *
 * Pure: receives `now` and the subject timezone as parameters.
 */
final class PeriodResolver
{
    public const CURRENT_MONTH = 'current_month';
    public const CURRENT_STATE = 'current_state';
    public const NEXT_7_DAYS = 'next_7_days';

    /**
     * @return array{type: string, from: ?string, to: ?string, label: string}
     */
    public static function resolve(string $kind, CarbonInterface $now, string $timezone = 'UTC'): array
    {
        $local = CarbonImmutable::instance($now)->setTimezone($timezone);

        return match ($kind) {
            self::CURRENT_MONTH => [
                'type' => self::CURRENT_MONTH,
                'from' => $local->startOfMonth()->toIso8601String(),
                'to' => $local->endOfMonth()->toIso8601String(),
                'label' => __('progress.periods.current_month'),
            ],
            self::NEXT_7_DAYS => [
                'type' => self::NEXT_7_DAYS,
                'from' => $local->toIso8601String(),
                'to' => $local->addDays(7)->toIso8601String(),
                'label' => __('progress.periods.next_7_days'),
            ],
            default => [
                'type' => self::CURRENT_STATE,
                'from' => null,
                'to' => null,
                'label' => __('progress.periods.current_state'),
            ],
        };
    }

    /**
     * Calendar-month boundaries in the subject timezone (DT-14).
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public static function currentMonthRange(CarbonInterface $now, string $timezone = 'UTC'): array
    {
        $local = CarbonImmutable::instance($now)->setTimezone($timezone);

        return [
            'from' => $local->startOfMonth(),
            'to' => $local->endOfMonth(),
        ];
    }
}
