<?php

namespace App\Services\Scheduling\Rules;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

final class TimezoneRules
{
    public static function resolveProfileTimezone(?string $profileTimezone, string $systemTimezone): string
    {
        self::assertValidTimezone($systemTimezone);

        return self::isValidTimezone($profileTimezone)
            ? $profileTimezone
            : $systemTimezone;
    }

    /**
     * Convert an instant for display without changing the instant itself.
     * `$now` is explicit context for temporal callers; presentation is independent of the clock.
     *
     * @return array{
     *     instant: DateTimeImmutable,
     *     sessionTimezone: string,
     *     viewerTimezone: string,
     *     showSessionZone: bool
     * }
     */
    public static function present(
        DateTimeInterface $instant,
        string $sessionTimezone,
        string $viewerTimezone,
        DateTimeInterface $now,
    ): array {
        self::assertValidTimezone($sessionTimezone);
        self::assertValidTimezone($viewerTimezone);

        $presentedInstant = DateTimeImmutable::createFromInterface($instant)
            ->setTimezone(new DateTimeZone($viewerTimezone));

        return [
            'instant' => $presentedInstant,
            'sessionTimezone' => $sessionTimezone,
            'viewerTimezone' => $viewerTimezone,
            'showSessionZone' => $sessionTimezone !== $viewerTimezone,
        ];
    }

    private static function assertValidTimezone(string $timezone): void
    {
        if (!self::isValidTimezone($timezone)) {
            throw new InvalidArgumentException("Invalid IANA timezone: {$timezone}");
        }
    }

    private static function isValidTimezone(?string $timezone): bool
    {
        return $timezone !== null
            && in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true);
    }
}
