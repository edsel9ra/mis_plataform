<?php

namespace App\Services\Scheduling\Rules;

use App\Enums\SessionStatus;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/** Pure eligibility rules for rescheduling an existing session. */
final class ChangeRules
{
    private const int DEFAULT_MINIMUM_NOTICE_MINUTES = 120;

    public static function cancellationLead(DateTimeInterface $scheduledAt, DateTimeInterface $now): int
    {
        $startAt = DateTimeImmutable::createFromInterface($scheduledAt)->setTimezone(new DateTimeZone('UTC'));
        $evaluatedAt = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone('UTC'));

        // Integer minutes are signed: positive means before the scheduled start,
        // negative means the cancellation happened after it.
        return intdiv($startAt->getTimestamp() - $evaluatedAt->getTimestamp(), 60);
    }

    public static function isLate(
        DateTimeInterface $scheduledAt,
        DateTimeInterface $now,
        int $minimumNoticeMinutes = self::DEFAULT_MINIMUM_NOTICE_MINUTES,
    ): bool {
        $startAt = DateTimeImmutable::createFromInterface($scheduledAt)->setTimezone(new DateTimeZone('UTC'));
        $evaluatedAt = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone('UTC'));

        return $startAt < $evaluatedAt->add(new DateInterval("PT{$minimumNoticeMinutes}M"));
    }

    public static function canReschedule(
        string|SessionStatus $sessionStatus,
        DateTimeInterface $scheduledAt,
        DateTimeInterface $now,
        int $minimumNoticeMinutes = self::DEFAULT_MINIMUM_NOTICE_MINUTES,
    ): bool {
        return self::rescheduleBlocker($sessionStatus, $scheduledAt, $now, $minimumNoticeMinutes) === null;
    }

    public static function rescheduleBlocker(
        string|SessionStatus $sessionStatus,
        DateTimeInterface $scheduledAt,
        DateTimeInterface $now,
        int $minimumNoticeMinutes = self::DEFAULT_MINIMUM_NOTICE_MINUTES,
    ): ?string {
        $status = $sessionStatus instanceof SessionStatus
            ? $sessionStatus->value
            : $sessionStatus;

        if (! in_array($status, [SessionStatus::Scheduled->value, SessionStatus::InProgress->value], true)) {
            return BlockReason::TERMINAL_STATUS;
        }

        $utc = new DateTimeZone('UTC');
        $startAt = DateTimeImmutable::createFromInterface($scheduledAt)->setTimezone($utc);
        $evaluatedAt = DateTimeImmutable::createFromInterface($now)->setTimezone($utc);

        if ($status === SessionStatus::InProgress->value || $startAt <= $evaluatedAt) {
            return BlockReason::ALREADY_STARTED;
        }

        if ($startAt < $evaluatedAt->add(new DateInterval("PT{$minimumNoticeMinutes}M"))) {
            return BlockReason::INSUFFICIENT_NOTICE;
        }

        return null;
    }

    public static function cancelBlocker(string|SessionStatus $sessionStatus): ?string
    {
        $status = $sessionStatus instanceof SessionStatus
            ? $sessionStatus->value
            : $sessionStatus;

        return in_array($status, [SessionStatus::Scheduled->value, SessionStatus::InProgress->value], true)
            ? null
            : BlockReason::TERMINAL_STATUS;
    }
}
