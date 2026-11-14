<?php

uses(
    Tests\TestCase::class,
)->in('Feature', 'Unit');

/**
 * Persisted timestamp columns hold app.timezone wall time. Fixtures that mean
 * "this instant" must be written with the Mexico_City wall representation so
 * Eloquent stores the right raw value.
 */
function dbInstant(string|DateTimeInterface $time): Carbon\CarbonImmutable
{
    $instant = $time instanceof DateTimeInterface
        ? Carbon\CarbonImmutable::instance($time)
        : Carbon\CarbonImmutable::parse($time, 'UTC');

    return $instant->setTimezone((string) config('app.timezone'));
}
