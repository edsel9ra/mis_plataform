<?php

use App\Services\Scheduling\Rules\ReminderRules;
use Carbon\CarbonImmutable;

it('returns only the reminder horizons reached within the scheduler window', function (): void {
    $now = CarbonImmutable::parse('2026-10-13T12:00:00Z');

    expect(ReminderRules::dueHorizons(CarbonImmutable::parse('2026-10-14T12:00:00Z'), $now))
        ->toBe([24])
        ->and(ReminderRules::dueHorizons(CarbonImmutable::parse('2026-10-14T12:00:30Z'), $now))
        ->toBe([24])
        ->and(ReminderRules::dueHorizons(CarbonImmutable::parse('2026-10-14T12:01:01Z'), $now))
        ->toBe([])
        ->and(ReminderRules::dueHorizons(CarbonImmutable::parse('2026-10-13T14:00:00Z'), $now))
        ->toBe([2])
        ->and(ReminderRules::dueHorizons(CarbonImmutable::parse('2026-10-13T13:00:00Z'), $now))
        ->toBe([1])
        ->and(ReminderRules::dueHorizons(CarbonImmutable::parse('2026-10-14T12:02:00Z'), $now))
        ->toBe([]);
});

it('treats imminent sessions as strictly less than twenty-four hours away', function (): void {
    $now = CarbonImmutable::parse('2026-10-13T12:00:00Z');

    expect(ReminderRules::isImminent(CarbonImmutable::parse('2026-10-14T11:59:59Z'), $now))
        ->toBeTrue()
        ->and(ReminderRules::isImminent(CarbonImmutable::parse('2026-10-14T12:00:00Z'), $now))
        ->toBeFalse()
        ->and(ReminderRules::isImminent(CarbonImmutable::parse('2026-10-13T11:59:59Z'), $now))
        ->toBeFalse();
});

it('keys reminders by the current schedule and keys changes by stable event id', function (): void {
    $originalSchedule = CarbonImmutable::parse('2026-10-14T12:00:00Z');
    $rescheduled = CarbonImmutable::parse('2026-10-15T12:00:00Z');

    $originalKey = ReminderRules::reminderKey('session-1', 'user-1', 24, $originalSchedule);

    expect(ReminderRules::reminderKey('session-1', 'user-1', 24, $originalSchedule))
        ->toBe($originalKey)
        ->and(ReminderRules::reminderKey(
            'session-1',
            'user-1',
            24,
            CarbonImmutable::parse('2026-10-14T14:00:00+02:00'),
        ))
        ->toBe($originalKey)
        ->and(ReminderRules::reminderKey('session-1', 'user-1', 24, $rescheduled))
        ->not->toBe($originalKey)
        ->and(ReminderRules::changeKey('session-1', 'event-1', 'user-1'))
        ->toBe(ReminderRules::changeKey('session-1', 'event-1', 'user-1'))
        ->and(ReminderRules::changeKey('session-1', 'event-2', 'user-1'))
        ->not->toBe(ReminderRules::changeKey('session-1', 'event-1', 'user-1'));
});

it('retries failed delivery but suppresses pending and sent delivery', function (): void {
    expect(ReminderRules::shouldAttempt(null))->toBeTrue()
        ->and(ReminderRules::shouldAttempt('pending'))->toBeFalse()
        ->and(ReminderRules::shouldAttempt('sent'))->toBeFalse()
        ->and(ReminderRules::shouldAttempt('failed'))->toBeTrue();
});
