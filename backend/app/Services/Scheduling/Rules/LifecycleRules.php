<?php

namespace App\Services\Scheduling\Rules;

use App\Enums\SessionStatus;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/** Pure state transition rules for a mentorship session. */
final class LifecycleRules
{
    private const int AUTO_CLOSE_HOURS = 48;

    public static function sessionEnd(DateTimeInterface $startAt, int $durationMinutes): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($startAt)
            ->setTimezone(new DateTimeZone('UTC'))
            ->add(new DateInterval("PT{$durationMinutes}M"));
    }

    public static function next(
        SessionStatus $status,
        DateTimeInterface $startAt,
        DateTimeInterface $endAt,
        DateTimeInterface $now,
    ): ?SessionStatus {
        if ($status->isTerminal()) {
            return null;
        }

        $deadline = DateTimeImmutable::createFromInterface($endAt)
            ->setTimezone(new DateTimeZone('UTC'))
            ->add(new DateInterval('PT'.self::AUTO_CLOSE_HOURS.'H'));
        $evaluatedAt = DateTimeImmutable::createFromInterface($now)
            ->setTimezone(new DateTimeZone('UTC'));

        if ($evaluatedAt >= $deadline) {
            return SessionStatus::NotCompleted;
        }

        if ($status === SessionStatus::Scheduled
            && $evaluatedAt >= DateTimeImmutable::createFromInterface($startAt)) {
            return SessionStatus::InProgress;
        }

        return null;
    }

    public static function completeBlocker(
        string|SessionStatus $sessionStatus,
        ?DateTimeInterface $startAt,
        ?DateTimeInterface $endAt,
        DateTimeInterface $now,
        int $closeAfterHours = self::AUTO_CLOSE_HOURS,
    ): ?string {
        $status = $sessionStatus instanceof SessionStatus
            ? $sessionStatus
            : SessionStatus::from($sessionStatus);

        if ($status->isTerminal()) {
            return BlockReason::TERMINAL_STATUS;
        }

        if ($startAt === null || $endAt === null) {
            return BlockReason::INVALID_SCHEDULE;
        }

        $utc = new DateTimeZone('UTC');
        $start = DateTimeImmutable::createFromInterface($startAt)->setTimezone($utc);
        $end = DateTimeImmutable::createFromInterface($endAt)->setTimezone($utc);
        $evaluatedAt = DateTimeImmutable::createFromInterface($now)->setTimezone($utc);
        $deadline = $end->add(new DateInterval("PT{$closeAfterHours}H"));

        if ($evaluatedAt < $end) {
            return BlockReason::NOT_ENDED;
        }

        if ($evaluatedAt >= $deadline) {
            return BlockReason::WINDOW_CLOSED;
        }

        if ($status === SessionStatus::Scheduled && $evaluatedAt < $start) {
            return BlockReason::NOT_STARTED;
        }

        return null;
    }
}
