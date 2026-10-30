<?php

namespace App\Services\Scheduling\Rules;

use App\Enums\AvailabilityMode;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/** Pure availability predicates; all dates and the reference clock are supplied by the caller. */
final class AvailabilityRules
{
    /**
     * @param list<array{day_of_week: int, start_time: string|DateTimeInterface, end_time: string|DateTimeInterface, timezone?: string}> $windows
     * @param list<array{mode: string|AvailabilityMode, start_date: string|DateTimeInterface, end_date?: string|DateTimeInterface|null, start_time?: string|DateTimeInterface|null, end_time?: string|DateTimeInterface|null, timezone?: string, reason?: ?string}> $exceptions
     * @param array{start: DateTimeInterface, end: DateTimeInterface} $interval
     * @return array{allowed: bool, reason: ?string}
     */
    public static function check(
        array $windows,
        array $exceptions,
        array $interval,
        DateTimeInterface $now,
        string $timezone,
    ): array {
        self::assertTimezone($timezone);

        $start = DateTimeImmutable::createFromInterface($interval['start']);
        $end = DateTimeImmutable::createFromInterface($interval['end']);

        if ($end <= $start) {
            throw new InvalidArgumentException('Availability interval end must be after its start.');
        }

        if ($start <= DateTimeImmutable::createFromInterface($now)) {
            return ['allowed' => false, 'reason' => 'not_in_future'];
        }

        $hasWindows = $windows !== [];
        $fitsWindow = ! $hasWindows;

        foreach ($windows as $window) {
            if (self::windowContains($window, $start, $end, $timezone)) {
                $fitsWindow = true;
                break;
            }
        }

        $hasOpenException = false;

        foreach ($exceptions as $exception) {
            $mode = self::mode($exception['mode']);

            if ($mode === AvailabilityMode::Block && self::exceptionOverlaps($exception, $start, $end, $timezone)) {
                return ['allowed' => false, 'reason' => 'blocked'];
            }

            if ($mode === AvailabilityMode::Open && self::exceptionContains($exception, $start, $end, $timezone)) {
                $hasOpenException = true;
            }
        }

        if ($fitsWindow || $hasOpenException) {
            return ['allowed' => true, 'reason' => null];
        }

        return ['allowed' => false, 'reason' => 'outside_availability'];
    }

    /**
     * @param list<array{day_of_week: int, start_time: string|DateTimeInterface, end_time: string|DateTimeInterface, timezone?: string}> $windows
     * @param list<array{mode: string|AvailabilityMode, start_date: string|DateTimeInterface, end_date?: string|DateTimeInterface|null, start_time?: string|DateTimeInterface|null, end_time?: string|DateTimeInterface|null, timezone?: string, reason?: ?string}> $exceptions
     * @param list<array{start: DateTimeInterface, end: DateTimeInterface}> $busy
     * @param array{start: DateTimeInterface, end: DateTimeInterface} $range
     * @return list<array{start: DateTimeImmutable, end: DateTimeImmutable}>
     */
    public static function freeSlots(
        array $windows,
        array $exceptions,
        array $busy,
        array $range,
        int $duration,
        string $viewerTimezone,
        DateTimeInterface $now,
    ): array {
        self::assertTimezone($viewerTimezone);

        if ($duration <= 0) {
            throw new InvalidArgumentException('Slot duration must be a positive number of minutes.');
        }

        $rangeStart = DateTimeImmutable::createFromInterface($range['start']);
        $rangeEnd = DateTimeImmutable::createFromInterface($range['end']);
        if ($rangeEnd <= $rangeStart) {
            throw new InvalidArgumentException('Availability range end must be after its start.');
        }

        $zone = new DateTimeZone($viewerTimezone);
        $nowInstant = DateTimeImmutable::createFromInterface($now);
        $candidate = self::ceilToHalfHour($rangeStart->setTimezone($zone));
        $slots = [];

        while ($candidate < $rangeEnd) {
            $end = $candidate->modify(sprintf('+%d minutes', $duration));

            if ($end > $rangeEnd) {
                break;
            }

            if ($candidate > $nowInstant
                && ! self::overlapsBusy($candidate, $end, $busy)
                && self::check($windows, $exceptions, ['start' => $candidate, 'end' => $end], $nowInstant, $viewerTimezone)['allowed']) {
                $slots[] = ['start' => $candidate, 'end' => $end];
            }

            $candidate = $candidate->modify('+30 minutes');
        }

        return $slots;
    }

    /** @param array{day_of_week: int, start_time: string|DateTimeInterface, end_time: string|DateTimeInterface, timezone?: string} $window */
    private static function windowContains(
        array $window,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $defaultTimezone,
    ): bool {
        $timezone = $window['timezone'] ?? $defaultTimezone;
        self::assertTimezone($timezone);
        $localStart = $start->setTimezone(new DateTimeZone($timezone));
        $localEnd = $end->setTimezone(new DateTimeZone($timezone));

        if ($localStart->format('Y-m-d') !== $localEnd->format('Y-m-d')
            || (int) $localStart->format('w') !== (int) $window['day_of_week']) {
            return false;
        }

        $windowStart = self::localDateTime($localStart->format('Y-m-d'), $window['start_time'], $timezone);
        $windowEnd = self::localDateTime($localStart->format('Y-m-d'), $window['end_time'], $timezone);

        return $start >= $windowStart && $end <= $windowEnd;
    }

    /** @param array<string, mixed> $exception */
    private static function exceptionOverlaps(
        array $exception,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $defaultTimezone,
    ): bool {
        foreach (self::exceptionIntervals($exception, $start, $end, $defaultTimezone) as [$exceptionStart, $exceptionEnd]) {
            if (OverlapDetector::overlaps($start, $end, $exceptionStart, $exceptionEnd)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $exception */
    private static function exceptionContains(
        array $exception,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $defaultTimezone,
    ): bool {
        foreach (self::exceptionIntervals($exception, $start, $end, $defaultTimezone) as [$exceptionStart, $exceptionEnd]) {
            if ($start >= $exceptionStart && $end <= $exceptionEnd) {
                return true;
            }
        }

        return false;
    }

    /**
     * Creates only exception intervals which could intersect the candidate. Dates are inclusive;
     * a missing time pair denotes the complete inclusive date range.
     *
     * @param array<string, mixed> $exception
     * @return list<array{0: DateTimeImmutable, 1: DateTimeImmutable}>
     */
    private static function exceptionIntervals(
        array $exception,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $defaultTimezone,
    ): array {
        $timezone = $exception['timezone'] ?? $defaultTimezone;
        self::assertTimezone($timezone);
        $zone = new DateTimeZone($timezone);
        $firstDate = self::dateString($exception['start_date']);
        $lastDate = isset($exception['end_date']) && $exception['end_date'] !== null
            ? self::dateString($exception['end_date'])
            : $firstDate;
        $allDay = ($exception['start_time'] ?? null) === null || ($exception['end_time'] ?? null) === null;

        if ($allDay) {
            $exceptionStart = new DateTimeImmutable($firstDate.' 00:00:00', $zone);
            $exceptionEnd = (new DateTimeImmutable($lastDate.' 00:00:00', $zone))->modify('+1 day');

            return OverlapDetector::overlaps($start, $end, $exceptionStart, $exceptionEnd)
                ? [[$exceptionStart, $exceptionEnd]]
                : [];
        }

        $localStart = $start->setTimezone($zone)->modify('-1 day')->format('Y-m-d');
        $localEnd = $end->setTimezone($zone)->format('Y-m-d');
        $cursor = max($firstDate, $localStart);
        $lastRelevantDate = min($lastDate, $localEnd);
        $intervals = [];

        while ($cursor <= $lastRelevantDate) {
            $exceptionStart = self::localDateTime($cursor, $exception['start_time'], $timezone);
            $exceptionEnd = self::localDateTime($cursor, $exception['end_time'], $timezone);
            if ($exceptionEnd < $exceptionStart) {
                $exceptionEnd = $exceptionEnd->modify('+1 day');
            }

            if ($exceptionEnd > $exceptionStart
                && OverlapDetector::overlaps($start, $end, $exceptionStart, $exceptionEnd)) {
                $intervals[] = [$exceptionStart, $exceptionEnd];
            }

            $cursor = (new DateTimeImmutable($cursor.' 00:00:00', $zone))->modify('+1 day')->format('Y-m-d');
        }

        return $intervals;
    }

    /** @param list<array{start: DateTimeInterface, end: DateTimeInterface}> $busy */
    private static function overlapsBusy(DateTimeImmutable $start, DateTimeImmutable $end, array $busy): bool
    {
        foreach ($busy as $interval) {
            if (OverlapDetector::overlaps(
                $start,
                $end,
                DateTimeImmutable::createFromInterface($interval['start']),
                DateTimeImmutable::createFromInterface($interval['end']),
            )) {
                return true;
            }
        }

        return false;
    }

    private static function ceilToHalfHour(DateTimeImmutable $instant): DateTimeImmutable
    {
        $minute = (int) $instant->format('i');
        $roundedMinute = $minute < 30 ? 0 : 30;
        $rounded = $instant->setTime((int) $instant->format('H'), $roundedMinute, 0);

        if ($rounded < $instant) {
            $rounded = $rounded->modify('+30 minutes');
        }

        return $rounded;
    }

    private static function localDateTime(string $date, string|DateTimeInterface $time, string $timezone): DateTimeImmutable
    {
        $timeString = $time instanceof DateTimeInterface ? $time->format('H:i:s') : $time;

        return new DateTimeImmutable($date.' '.$timeString, new DateTimeZone($timezone));
    }

    private static function dateString(string|DateTimeInterface $date): string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }

    private static function mode(string|AvailabilityMode $mode): AvailabilityMode
    {
        return $mode instanceof AvailabilityMode ? $mode : AvailabilityMode::from($mode);
    }

    private static function assertTimezone(string $timezone): void
    {
        try {
            new DateTimeZone($timezone);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException("Invalid IANA timezone: {$timezone}", previous: $exception);
        }
    }
}
