<?php

use App\Services\Progress\Support\PeriodResolver;
use Carbon\CarbonImmutable;

uses()->group('progress');

it('resolves the current calendar month period', function () {
    $now = CarbonImmutable::parse('2026-06-15T12:00:00Z');
    $period = PeriodResolver::resolve(PeriodResolver::CURRENT_MONTH, $now, 'UTC');

    expect($period['type'])->toBe('current_month')
        ->and($period['from'])->toStartWith('2026-06-01')
        ->and($period['to'])->toStartWith('2026-06-30')
        ->and($period['label'])->toBeString()->not->toBe('');
});

it('resolves the current-state period without a range', function () {
    $period = PeriodResolver::resolve(PeriodResolver::CURRENT_STATE, CarbonImmutable::now(), 'UTC');

    expect($period['type'])->toBe('current_state')
        ->and($period['from'])->toBeNull()
        ->and($period['to'])->toBeNull();
});

it('resolves the next 7 days window in the subject timezone', function () {
    $now = CarbonImmutable::parse('2026-06-15T12:00:00Z');
    $period = PeriodResolver::resolve(PeriodResolver::NEXT_7_DAYS, $now, 'Europe/Madrid');

    expect($period['type'])->toBe('next_7_days')
        ->and($period['from'])->toContain('+02:00')
        ->and(abs(CarbonImmutable::parse($period['to'])->diffInDays(CarbonImmutable::parse($period['from']))))->toBe(7.0);
});

it('computes month boundaries in the subject timezone', function () {
    $now = CarbonImmutable::parse('2026-06-15T12:00:00Z');
    $range = PeriodResolver::currentMonthRange($now, 'Europe/Madrid');

    expect($range['from']->timezoneName)->toBe('Europe/Madrid')
        ->and($range['from']->day)->toBe(1)
        ->and($range['to']->day)->toBe(30);
});
