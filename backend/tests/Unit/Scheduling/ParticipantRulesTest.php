<?php

use App\Services\Scheduling\Rules\JoinDecision;
use App\Services\Scheduling\Rules\ParticipantRules;
use Carbon\CarbonImmutable;

it('excludes a member from this collective session when an active own session overlaps', function (): void {
    $now = CarbonImmutable::parse('2026-10-12T09:00:00Z');
    $session = [
        'start' => CarbonImmutable::parse('2026-10-12T10:00:00Z'),
        'end' => CarbonImmutable::parse('2026-10-12T11:00:00Z'),
        'status' => 'scheduled',
    ];

    expect(ParticipantRules::canJoinAfterBooking(
        $session,
        [[
            'start' => CarbonImmutable::parse('2026-10-12T10:30:00Z'),
            'end' => CarbonImmutable::parse('2026-10-12T11:30:00Z'),
            'status' => 'scheduled',
        ]],
        $now,
    ))->toBe(JoinDecision::ExcludedForSession);
});

it('ignores canceled and not-completed own sessions when deciding whether a member can join', function (): void {
    $now = CarbonImmutable::parse('2026-10-12T09:00:00Z');
    $session = [
        'start' => CarbonImmutable::parse('2026-10-12T10:00:00Z'),
        'end' => CarbonImmutable::parse('2026-10-12T11:00:00Z'),
        'status' => 'scheduled',
    ];
    $ownSessions = [
        [
            'start' => CarbonImmutable::parse('2026-10-12T10:00:00Z'),
            'end' => CarbonImmutable::parse('2026-10-12T10:30:00Z'),
            'status' => 'canceled',
        ],
        [
            'start' => CarbonImmutable::parse('2026-10-12T10:30:00Z'),
            'end' => CarbonImmutable::parse('2026-10-12T11:00:00Z'),
            'status' => 'not_completed',
        ],
    ];

    expect(ParticipantRules::canJoinAfterBooking($session, $ownSessions, $now))
        ->toBe(JoinDecision::Join);
});

it('does not apply a permanent exclusion when the personal session only touches the collective interval', function (): void {
    $now = CarbonImmutable::parse('2026-10-12T09:00:00Z');
    $session = [
        'start' => CarbonImmutable::parse('2026-10-12T10:00:00Z'),
        'end' => CarbonImmutable::parse('2026-10-12T11:00:00Z'),
        'status' => 'scheduled',
    ];
    $ownSessions = [[
        'start' => CarbonImmutable::parse('2026-10-12T11:00:00Z'),
        'end' => CarbonImmutable::parse('2026-10-12T12:00:00Z'),
        'status' => 'scheduled',
    ]];

    expect(ParticipantRules::canJoinAfterBooking($session, $ownSessions, $now))
        ->toBe(JoinDecision::Join);
});

it('does not add a member after the collective session end anchor', function (): void {
    $endAt = CarbonImmutable::parse('2026-10-12T11:00:00Z');
    $session = [
        'start' => CarbonImmutable::parse('2026-10-12T10:00:00Z'),
        'end' => $endAt,
        'status' => 'in_progress',
    ];

    expect(ParticipantRules::canJoinAfterBooking($session, [], $endAt))
        ->toBe(JoinDecision::NotEligible);
});

it('resolves participants at the scheduled end including the mentor and applying permanent exclusions', function (): void {
    $endAt = CarbonImmutable::parse('2026-10-12T11:00:00Z');
    $now = CarbonImmutable::parse('2026-10-13T12:00:00Z');
    $session = [
        'mentor_id' => 'mentor-1',
        'excluded_user_ids' => ['excluded-1'],
    ];
    $attendees = [
        ['user_id' => 'member-1', 'created_at' => $endAt->subHour(), 'left_at' => null],
        ['user_id' => 'departed-later', 'created_at' => $endAt->subHour(), 'left_at' => $endAt->addSecond()],
        ['user_id' => 'joined-at-anchor', 'created_at' => $endAt, 'left_at' => null],
        ['user_id' => 'left-at-anchor', 'created_at' => $endAt->subHour(), 'left_at' => $endAt],
        ['user_id' => 'joined-later', 'created_at' => $endAt->addSecond(), 'left_at' => null],
        ['user_id' => 'excluded-1', 'created_at' => $endAt->subHour(), 'left_at' => null],
        ['user_id' => 'mentor-1', 'created_at' => $endAt->subHour(), 'left_at' => null],
    ];

    expect(ParticipantRules::participantsAtEnd($session, $attendees, $endAt, $now))
        ->toBe(['member-1', 'departed-later', 'joined-at-anchor', 'mentor-1']);
});
