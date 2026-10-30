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
}
