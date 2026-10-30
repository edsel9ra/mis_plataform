<?php

namespace App\Services\Scheduling\Rules;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

final class ReminderRules
{
    private const array HORIZONS_HOURS = [24, 2, 1];

    private const int SCHEDULER_WINDOW_MINUTES = 1;

    public static function dueHorizons(
        DateTimeInterface $scheduledAt,
        DateTimeInterface $now,
        array $horizons = self::HORIZONS_HOURS,
    ): array {
        $utc = new DateTimeZone('UTC');
        $scheduled = DateTimeImmutable::createFromInterface($scheduledAt)->setTimezone($utc);
        $evaluatedAt = DateTimeImmutable::createFromInterface($now)->setTimezone($utc);
        $window = new DateInterval('PT'.self::SCHEDULER_WINDOW_MINUTES.'M');
        $dueHorizons = [];

        foreach ($horizons as $horizon) {
            $reminderAt = $scheduled->sub(new DateInterval("PT{$horizon}H"));

            if ($evaluatedAt >= $reminderAt->sub($window) && $evaluatedAt <= $reminderAt->add($window)) {
                $dueHorizons[] = $horizon;
            }
        }

        return $dueHorizons;
    }

    public static function isImminent(
        DateTimeInterface $scheduledAt,
        DateTimeInterface $now,
        int $imminentBeforeHours = 24,
    ): bool {
        $utc = new DateTimeZone('UTC');
        $scheduled = DateTimeImmutable::createFromInterface($scheduledAt)->setTimezone($utc);
        $evaluatedAt = DateTimeImmutable::createFromInterface($now)->setTimezone($utc);

        return $scheduled > $evaluatedAt
            && $scheduled < $evaluatedAt->add(new DateInterval("PT{$imminentBeforeHours}H"));
    }

    public static function reminderKey(
        string $sessionId,
        string $userId,
        int|string $horizon,
        DateTimeInterface $scheduledAt,
    ): string {
        $scheduled = DateTimeImmutable::createFromInterface($scheduledAt)
            ->setTimezone(new DateTimeZone('UTC'));

        return sprintf(
            'session_reminder:%s:%s:%s:%s',
            $sessionId,
            $userId,
            $horizon,
            $scheduled->format('Y-m-d\TH:i:s.u\Z'),
        );
    }

    public static function changeKey(string $sessionId, string $eventId, string $userId): string
    {
        return sprintf('session_change:%s:%s:%s', $sessionId, $eventId, $userId);
    }

    public static function shouldAttempt(?string $status): bool
    {
        if ($status === null || $status === 'failed') {
            return true;
        }

        if (in_array($status, ['pending', 'sent'], true)) {
            return false;
        }

        throw new InvalidArgumentException("Unsupported notification delivery status: {$status}");
    }
}
