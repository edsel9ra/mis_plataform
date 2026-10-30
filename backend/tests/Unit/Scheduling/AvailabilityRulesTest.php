<?php

use App\Services\Scheduling\Rules\AvailabilityRules;
use Carbon\CarbonImmutable;

it('requires the entire proposed session to fit inside one recurring window', function (): void {
    $now = CarbonImmutable::parse('2026-10-12T08:00:00Z');
    $window = [
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'timezone' => 'UTC',
    ];

    expect(AvailabilityRules::check(
        [$window],
        [],
        [
            'start' => CarbonImmutable::parse('2026-10-12T10:00:00Z'),
            'end' => CarbonImmutable::parse('2026-10-12T11:00:00Z'),
        ],
        $now,
        'UTC',
    ))->toMatchArray(['allowed' => true, 'reason' => null]);

    expect(AvailabilityRules::check(
        [$window],
        [],
        [
            'start' => CarbonImmutable::parse('2026-10-12T10:30:00Z'),
            'end' => CarbonImmutable::parse('2026-10-12T11:30:00Z'),
        ],
        $now,
        'UTC',
    ))->toMatchArray(['allowed' => false, 'reason' => 'outside_availability']);
});

it('allows a mentor without windows unless an all-day or timed block applies', function (): void {
    $now = CarbonImmutable::parse('2026-10-12T08:00:00Z');
    $interval = [
        'start' => CarbonImmutable::parse('2026-10-12T10:00:00Z'),
        'end' => CarbonImmutable::parse('2026-10-12T11:00:00Z'),
    ];
    $block = [
        'mode' => 'block',
        'start_date' => '2026-10-12',
        'end_date' => '2026-10-14',
        'start_time' => null,
        'end_time' => null,
        'timezone' => 'UTC',
        'reason' => 'private reason',
    ];

    expect(AvailabilityRules::check([], [], $interval, $now, 'UTC'))
        ->toMatchArray(['allowed' => true, 'reason' => null])
        ->and(AvailabilityRules::check([], [$block], $interval, $now, 'UTC'))
        ->toMatchArray(['allowed' => false, 'reason' => 'blocked']);
});

it('gives blocks precedence over windows and open exceptions precedence over windows', function (): void {
    $now = CarbonImmutable::parse('2026-10-12T08:00:00Z');
    $interval = [
        'start' => CarbonImmutable::parse('2026-10-12T10:00:00Z'),
        'end' => CarbonImmutable::parse('2026-10-12T11:00:00Z'),
    ];
    $window = [
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'timezone' => 'UTC',
    ];
    $block = [
        'mode' => 'block', 'start_date' => '2026-10-12', 'end_date' => null,
        'start_time' => '10:30:00', 'end_time' => '11:30:00', 'timezone' => 'UTC',
    ];
    $open = [
        'mode' => 'open', 'start_date' => '2026-10-12', 'end_date' => null,
        'start_time' => '10:00:00', 'end_time' => '11:00:00', 'timezone' => 'UTC',
    ];

    expect(AvailabilityRules::check([$window], [$block], $interval, $now, 'UTC'))
        ->toMatchArray(['allowed' => false, 'reason' => 'blocked'])
        ->and(AvailabilityRules::check([], [$open], $interval, $now, 'UTC'))
        ->toMatchArray(['allowed' => true, 'reason' => null]);
});

it('returns private free slots on a thirty-minute grid in viewer timezone, excluding busy, blocked, and past slots', function (): void {
    $now = CarbonImmutable::parse('2026-10-12T09:10:00Z');
    $window = [
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'timezone' => 'UTC',
    ];
    $block = [
        'mode' => 'block', 'start_date' => '2026-10-12', 'end_date' => null,
        'start_time' => '10:00:00', 'end_time' => '10:30:00', 'timezone' => 'UTC',
        'reason' => 'do not expose',
    ];
    $slots = AvailabilityRules::freeSlots(
        [$window],
        [$block],
        [[
            'start' => CarbonImmutable::parse('2026-10-12T11:00:00Z'),
            'end' => CarbonImmutable::parse('2026-10-12T11:30:00Z'),
        ]],
        [
            'start' => CarbonImmutable::parse('2026-10-12T08:00:00Z'),
            'end' => CarbonImmutable::parse('2026-10-12T12:00:00Z'),
        ],
        30,
        'America/Los_Angeles',
        $now,
    );

    expect(array_map(fn (array $slot): string => $slot['start']->format('H:i'), $slots))
        ->toBe(['02:30', '03:30', '04:30'])
        ->and(array_map(fn (array $slot): string => $slot['start']->getTimezone()->getName(), $slots))
        ->toBe(['America/Los_Angeles', 'America/Los_Angeles', 'America/Los_Angeles'])
        ->and($slots[0])->not->toHaveKey('mode')
        ->and($slots[0])->not->toHaveKey('reason');
});

it('generates slots for an open exception outside usual windows and respects DST offsets', function (): void {
    $now = CarbonImmutable::parse('2026-03-08T07:30:00Z');
    $window = [
        'day_of_week' => 0,
        'start_time' => '00:00:00',
        'end_time' => '00:30:00',
        'timezone' => 'America/Los_Angeles',
    ];
    $open = [
        'mode' => 'open', 'start_date' => '2026-03-08', 'end_date' => null,
        'start_time' => '01:00:00', 'end_time' => '04:00:00', 'timezone' => 'America/Los_Angeles',
    ];
    $slots = AvailabilityRules::freeSlots(
        [$window],
        [$open],
        [],
        [
            'start' => CarbonImmutable::parse('2026-03-08T08:00:00Z'),
            'end' => CarbonImmutable::parse('2026-03-08T11:00:00Z'),
        ],
        30,
        'America/Los_Angeles',
        $now,
    );

    expect(array_map(fn (array $slot): string => $slot['start']->format('H:i P'), $slots))
        ->toBe(['00:00 -08:00', '01:00 -08:00', '01:30 -08:00', '03:00 -07:00', '03:30 -07:00']);
});

it('rejects a proposed interval at or before the supplied now', function (): void {
    $now = CarbonImmutable::parse('2026-10-12T10:00:00Z');

    expect(AvailabilityRules::check(
        [],
        [],
        [
            'start' => CarbonImmutable::parse('2026-10-12T09:30:00Z'),
            'end' => CarbonImmutable::parse('2026-10-12T10:30:00Z'),
        ],
        $now,
        'UTC',
    ))->toMatchArray(['allowed' => false, 'reason' => 'not_in_future']);
});
