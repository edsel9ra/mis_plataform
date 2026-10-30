<?php

use App\Enums\SessionStatus;
use App\Services\Scheduling\Rules\ChangeRules;

uses()->group('scheduling', 'rescheduling');

it('allows a scheduled session exactly at the two-hour notice threshold', function () {
    $now = new DateTimeImmutable('2026-10-13T10:00:00+00:00');
    $scheduledAt = new DateTimeImmutable('2026-10-13T12:00:00+00:00');

    expect(ChangeRules::canReschedule(SessionStatus::Scheduled, $scheduledAt, $now))->toBeTrue();
});

it('rejects sessions below the notice threshold or without a future start', function (DateTimeImmutable $scheduledAt) {
    $now = new DateTimeImmutable('2026-10-13T10:00:00+00:00');

    expect(ChangeRules::canReschedule(SessionStatus::Scheduled, $scheduledAt, $now))->toBeFalse();
})->with([
    'one second below two hours' => [new DateTimeImmutable('2026-10-13T11:59:59+00:00')],
    'at now' => [new DateTimeImmutable('2026-10-13T10:00:00+00:00')],
    'already elapsed' => [new DateTimeImmutable('2026-10-13T09:59:59+00:00')],
]);

it('rejects in-progress and terminal session statuses', function (SessionStatus $status) {
    $now = new DateTimeImmutable('2026-10-13T10:00:00+00:00');
    $scheduledAt = new DateTimeImmutable('2026-10-13T13:00:00+00:00');

    expect(ChangeRules::canReschedule($status, $scheduledAt, $now))->toBeFalse();
})->with([
    'in progress' => [SessionStatus::InProgress],
    'completed' => [SessionStatus::Completed],
    'canceled' => [SessionStatus::Canceled],
    'not completed' => [SessionStatus::NotCompleted],
]);

it('accepts a scheduled status string and a custom notice interval', function () {
    $now = new DateTimeImmutable('2026-10-13T10:00:00+00:00');
    $scheduledAt = new DateTimeImmutable('2026-10-13T11:00:00+00:00');

    expect(ChangeRules::canReschedule('scheduled', $scheduledAt, $now, 60))->toBeTrue();
});

it('calculates signed cancellation lead minutes and the strict two-hour late threshold', function () {
    $start = new DateTimeImmutable('2026-10-13T12:00:00+00:00');

    expect(ChangeRules::cancellationLead($start, new DateTimeImmutable('2026-10-13T10:00:00+00:00')))->toBe(120)
        ->and(ChangeRules::isLate($start, new DateTimeImmutable('2026-10-13T10:00:00+00:00')))->toBeFalse()
        ->and(ChangeRules::cancellationLead($start, new DateTimeImmutable('2026-10-13T10:00:01+00:00')))->toBe(119)
        ->and(ChangeRules::isLate($start, new DateTimeImmutable('2026-10-13T10:00:01+00:00')))->toBeTrue()
        ->and(ChangeRules::cancellationLead($start, new DateTimeImmutable('2026-10-13T12:30:00+00:00')))->toBe(-30)
        ->and(ChangeRules::isLate($start, new DateTimeImmutable('2026-10-13T12:30:00+00:00')))->toBeTrue();
});
