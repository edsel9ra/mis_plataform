<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Timestamp columns are stored timezone-less in the application timezone
 * (Eloquent writes the Carbon wall time). Raw strings from getAttributes()
 * or getRawOriginal() therefore represent app.timezone wall times, while
 * business rules operate on UTC instants. These helpers are the single
 * conversion point between the two representations.
 */
final class DbTime
{
    /**
     * Interpret a raw persisted timestamp (app.timezone wall time) as the
     * real instant it represents, returned in UTC.
     */
    public static function fromRaw(?string $raw): ?CarbonImmutable
    {
        if ($raw === null) {
            return null;
        }

        return CarbonImmutable::parse($raw, (string) config("app.timezone"))->utc();
    }

    /**
     * Format an instant for comparison against (or storage in) a
     * timezone-less timestamp column: app.timezone wall time.
     */
    public static function toRaw(DateTimeInterface $instant): string
    {
        return CarbonImmutable::instance($instant)
            ->setTimezone((string) config("app.timezone"))
            ->format("Y-m-d H:i:s");
    }
}
