<?php

use App\Enums\SessionStatus;
use App\Services\Scheduling\Rules\LifecycleRules;
use Carbon\CarbonImmutable;

it('calculates the scheduled end from the start and duration without mutating the start', function (): void {
    $startAt = CarbonImmutable::parse('2026-10-13T10:00:00Z');

    expect(LifecycleRules::sessionEnd($startAt, 90)->format(DATE_ATOM))
        ->toBe('2026-10-13T11:30:00+00:00')
        ->and($startAt->toIso8601String())->toBe('2026-10-13T10:00:00+00:00');
});

it('adds duration as elapsed minutes across a daylight-saving transition', function (): void {
    $startAt = CarbonImmutable::parse('2026-03-08T01:30:00', 'America/New_York');

    expect(LifecycleRules::sessionEnd($startAt, 90)->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM))
        ->toBe('2026-03-08T08:00:00+00:00');
});

it('does not start a scheduled session before its scheduled start', function (): void {
    $startAt = CarbonImmutable::parse('2026-10-13T10:00:00Z');

    expect(LifecycleRules::next(
        SessionStatus::Scheduled,
        $startAt,
        $startAt->addHour(),
        $startAt->subSecond(),
    ))->toBeNull();
});

it('starts a scheduled session exactly at its scheduled start', function (): void {
    $startAt = CarbonImmutable::parse('2026-10-13T10:00:00Z');

    expect(LifecycleRules::next(
        SessionStatus::Scheduled,
        $startAt,
        $startAt->addHour(),
        $startAt,
    ))->toBe(SessionStatus::InProgress);
});

it('keeps a scheduled session in progress between start and the automatic close deadline', function (): void {
    $startAt = CarbonImmutable::parse('2026-10-13T10:00:00Z');
    $endAt = $startAt->addHour();

    expect(LifecycleRules::next(
        SessionStatus::Scheduled,
        $startAt,
        $endAt,
        $endAt->addHours(48)->subSecond(),
    ))->toBe(SessionStatus::InProgress);
});

it('marks a scheduled session not completed at exactly end plus 48 hours', function (): void {
    $startAt = CarbonImmutable::parse('2026-10-13T10:00:00Z');
    $endAt = $startAt->addHour();

    expect(LifecycleRules::next(
        SessionStatus::Scheduled,
        $startAt,
        $endAt,
        $endAt->addHours(48),
    ))->toBe(SessionStatus::NotCompleted);
});

it('marks an in-progress session not completed at the exact deadline', function (): void {
    $startAt = CarbonImmutable::parse('2026-10-13T10:00:00Z');
    $endAt = $startAt->addHour();

    expect(LifecycleRules::next(
        SessionStatus::InProgress,
        $startAt,
        $endAt,
        $endAt->addHours(48),
    ))->toBe(SessionStatus::NotCompleted);
});

it('leaves an in-progress session unchanged before the automatic close deadline', function (): void {
    $startAt = CarbonImmutable::parse('2026-10-13T10:00:00Z');
    $endAt = $startAt->addHour();

    expect(LifecycleRules::next(
        SessionStatus::InProgress,
        $startAt,
        $endAt,
        $endAt->addHours(48)->subSecond(),
    ))->toBeNull();
});

it('does not change terminal session states', function (SessionStatus $status): void {
    $startAt = CarbonImmutable::parse('2026-10-13T10:00:00Z');
    $endAt = $startAt->addHour();

    expect(LifecycleRules::next(
        $status,
        $startAt,
        $endAt,
        $endAt->addHours(72),
    ))->toBeNull();
})->with([
    'completed' => SessionStatus::Completed,
    'canceled' => SessionStatus::Canceled,
    'not completed' => SessionStatus::NotCompleted,
]);
