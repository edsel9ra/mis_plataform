<?php

use App\Services\Scheduling\Rules\TimezoneRules;
use Carbon\CarbonImmutable;

it('resolves a profile timezone or falls back to the supplied system timezone', function (): void {
    expect(TimezoneRules::resolveProfileTimezone('Europe/Madrid', 'UTC'))->toBe('Europe/Madrid')
        ->and(TimezoneRules::resolveProfileTimezone(null, 'UTC'))->toBe('UTC')
        ->and(TimezoneRules::resolveProfileTimezone('', 'UTC'))->toBe('UTC')
        ->and(TimezoneRules::resolveProfileTimezone('Mars/Olympus_Mons', 'UTC'))->toBe('UTC');
});

it('rejects an invalid system timezone instead of returning an unusable fallback', function (): void {
    TimezoneRules::resolveProfileTimezone(null, 'Mars/Olympus_Mons');
})->throws(InvalidArgumentException::class);

it('presents the same instant in the viewer timezone and indicates when the session zone differs', function (): void {
    $instant = CarbonImmutable::parse('2026-06-15T12:00:00Z');
    $now = CarbonImmutable::parse('2026-06-15T11:00:00Z');

    $presented = TimezoneRules::present($instant, 'Europe/Madrid', 'America/Mexico_City', $now);

    expect($presented['instant']->format(DATE_ATOM))->toBe('2026-06-15T06:00:00-06:00')
        ->and($presented['instant']->getTimestamp())->toBe($instant->getTimestamp())
        ->and($presented['sessionTimezone'])->toBe('Europe/Madrid')
        ->and($presented['viewerTimezone'])->toBe('America/Mexico_City')
        ->and($presented['showSessionZone'])->toBeTrue();

    $sameZone = TimezoneRules::present($instant, 'UTC', 'UTC', $now);

    expect($sameZone['showSessionZone'])->toBeFalse()
        ->and($sameZone['instant']->getTimestamp())->toBe($instant->getTimestamp());
});

it('converts an instant across midnight without changing which day it represents for the viewer', function (): void {
    $instant = CarbonImmutable::parse('2026-06-15T00:30:00Z');
    $now = CarbonImmutable::parse('2026-06-14T23:00:00Z');

    $presented = TimezoneRules::present($instant, 'UTC', 'America/Los_Angeles', $now);

    expect($presented['instant']->format('Y-m-d H:i:s P'))->toBe('2026-06-14 17:30:00 -07:00')
        ->and($presented['instant']->getTimestamp())->toBe($instant->getTimestamp());
});

it('uses the explicit instant and timezone rules across a daylight-saving transition', function (): void {
    $beforeTransition = CarbonImmutable::parse('2026-03-08T09:30:00Z');
    $afterTransition = CarbonImmutable::parse('2026-03-08T10:30:00Z');
    $now = CarbonImmutable::parse('2026-03-08T08:00:00Z');

    $before = TimezoneRules::present($beforeTransition, 'UTC', 'America/Los_Angeles', $now);
    $after = TimezoneRules::present($afterTransition, 'UTC', 'America/Los_Angeles', $now);

    expect($before['instant']->format('Y-m-d H:i:s P'))->toBe('2026-03-08 01:30:00 -08:00')
        ->and($after['instant']->format('Y-m-d H:i:s P'))->toBe('2026-03-08 03:30:00 -07:00')
        ->and($before['instant']->getTimestamp())->toBe($beforeTransition->getTimestamp())
        ->and($after['instant']->getTimestamp())->toBe($afterTransition->getTimestamp());
});
