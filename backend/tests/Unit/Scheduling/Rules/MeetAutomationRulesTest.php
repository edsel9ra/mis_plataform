<?php

use App\Models\Session;
use App\Services\Scheduling\Rules\MeetAutomationRules;
use Carbon\CarbonImmutable;

it('only considers scheduled future sessions without a link or event eligible', function (): void {
    $now = CarbonImmutable::parse('2026-10-13 09:00:00', 'UTC');
    $eligible = Session::factory()->make([
        'status' => 'scheduled',
        'scheduled_at' => $now->addMinute(),
        'meet_link' => null,
        'meet_event_id' => null,
    ]);

    expect(MeetAutomationRules::eligible($eligible, $now))->toBeTrue();
});

it('excludes the exact start boundary, past sessions, non-scheduled states, and existing Meet data', function (): void {
    $now = CarbonImmutable::parse('2026-10-13 09:00:00', 'UTC');
    $cases = [
        ['scheduled', $now, null, null],
        ['scheduled', $now->subSecond(), null, null],
        ['canceled', $now->addHour(), null, null],
        ['not_completed', $now->addHour(), null, null],
        ['in_progress', $now->addHour(), null, null],
        ['completed', $now->addHour(), null, null],
        ['scheduled', $now->addHour(), 'https://meet.example/existing', null],
        ['scheduled', $now->addHour(), null, 'existing-event-id'],
    ];

    foreach ($cases as [$status, $scheduledAt, $link, $eventId]) {
        $session = Session::factory()->make([
            'status' => $status,
            'scheduled_at' => dbInstant($scheduledAt),
            'meet_link' => $link,
            'meet_event_id' => $eventId,
        ]);

        expect(MeetAutomationRules::eligible($session, $now))->toBeFalse(
            "Unexpected eligible case: {$status}, expected={$scheduledAt->toIso8601String()}, actual={$session->scheduled_at->toIso8601String()}, now={$now->toIso8601String()}, link=".($link ?? 'null').', event='.($eventId ?? 'null'),
        );
    }
});
