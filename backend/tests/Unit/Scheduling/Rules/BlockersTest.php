<?php

use App\Enums\AttendanceDecision;
use App\Enums\AttendanceRelation;
use App\Enums\SessionStatus;
use App\Services\Scheduling\Rules\AttendanceRules;
use App\Services\Scheduling\Rules\BlockReason;
use App\Services\Scheduling\Rules\ChangeRules;
use App\Services\Scheduling\Rules\LifecycleRules;
use Carbon\CarbonImmutable;

uses()->group('scheduling', 'rules-blockers');

$now = fn (): CarbonImmutable => CarbonImmutable::parse('2026-10-13T10:00:00Z');

it('rescheduleBlocker returns null exactly at the notice threshold', function () use ($now): void {
    $result = ChangeRules::rescheduleBlocker(
        SessionStatus::Scheduled,
        $now()->addMinutes(120),
        $now(),
    );

    expect($result)->toBeNull();
});

it('rescheduleBlocker returns insufficient_notice one second below the threshold', function () use ($now): void {
    $result = ChangeRules::rescheduleBlocker(
        'scheduled',
        $now()->addMinutes(120)->subSecond(),
        $now(),
    );

    expect($result)->toBe(BlockReason::INSUFFICIENT_NOTICE);
});

it('rescheduleBlocker returns already_started at or after the start and for in_progress', function (string|SessionStatus $status, CarbonImmutable $start) use ($now): void {
    expect(ChangeRules::rescheduleBlocker($status, $start, $now()))->toBe(BlockReason::ALREADY_STARTED);
})->with([
    'scheduled at now' => [SessionStatus::Scheduled, CarbonImmutable::parse('2026-10-13T10:00:00Z')],
    'scheduled in the past' => ['scheduled', CarbonImmutable::parse('2026-10-13T09:00:00Z')],
    'in progress future start' => [SessionStatus::InProgress, CarbonImmutable::parse('2026-10-13T15:00:00Z')],
]);

it('rescheduleBlocker returns terminal_status for terminal and unknown statuses', function (string|SessionStatus $status) use ($now): void {
    expect(ChangeRules::rescheduleBlocker($status, $now()->addDay(), $now()))->toBe(BlockReason::TERMINAL_STATUS);
})->with([
    'completed' => [SessionStatus::Completed],
    'canceled' => [SessionStatus::Canceled],
    'not completed' => ['not_completed'],
    'unknown string' => ['paused'],
]);

it('canReschedule is exactly rescheduleBlocker === null over a grid of inputs', function (string|SessionStatus $status, int $startOffsetSeconds) use ($now): void {
    $evaluated = $now();
    $start = $evaluated->addSeconds($startOffsetSeconds);

    expect(ChangeRules::canReschedule($status, $start, $evaluated))
        ->toBe(ChangeRules::rescheduleBlocker($status, $start, $evaluated) === null);
})->with([
    ...array_map(
        fn (string|SessionStatus $s): array => [$s],
        array_merge(SessionStatus::cases(), ['paused']),
    ),
])->with([
    [-3600], [0], [1], [7199], [7200], [7201],
]);

it('cancelBlocker allows scheduled and in_progress and blocks the rest', function (string|SessionStatus $status, ?string $expected): void {
    expect(ChangeRules::cancelBlocker($status))->toBe($expected);
})->with([
    'scheduled' => ['scheduled', null],
    'in progress enum' => [SessionStatus::InProgress, null],
    'completed' => [SessionStatus::Completed, BlockReason::TERMINAL_STATUS],
    'canceled' => ['canceled', BlockReason::TERMINAL_STATUS],
    'not completed' => [SessionStatus::NotCompleted, BlockReason::TERMINAL_STATUS],
    'unknown string' => ['paused', BlockReason::TERMINAL_STATUS],
]);

it('completeBlocker flags terminal statuses before checking the schedule', function (SessionStatus $status) use ($now): void {
    expect(LifecycleRules::completeBlocker($status, null, null, $now()))->toBe(BlockReason::TERMINAL_STATUS);
})->with([
    'completed' => [SessionStatus::Completed],
    'canceled' => [SessionStatus::Canceled],
    'not completed' => [SessionStatus::NotCompleted],
]);

it('completeBlocker requires a valid schedule', function () use ($now): void {
    expect(LifecycleRules::completeBlocker('scheduled', null, $now(), $now()))->toBe(BlockReason::INVALID_SCHEDULE);
    expect(LifecycleRules::completeBlocker('scheduled', $now(), null, $now()))->toBe(BlockReason::INVALID_SCHEDULE);
});

it('completeBlocker reports not_ended until the end instant and allows at end', function () use ($now): void {
    $start = $now()->subHours(2);
    $end = $now();

    expect(LifecycleRules::completeBlocker('in_progress', $start, $end, $end->subSecond()))
        ->toBe(BlockReason::NOT_ENDED);
    expect(LifecycleRules::completeBlocker('in_progress', $start, $end, $end))
        ->toBeNull();
});

it('completeBlocker reports window_closed at the deadline and allows just before it', function () use ($now): void {
    $start = $now()->subHours(2);
    $end = $now()->subHour();
    $deadline = $end->addHours(48);

    expect(LifecycleRules::completeBlocker('in_progress', $start, $end, $deadline->subSecond()))
        ->toBeNull();
    expect(LifecycleRules::completeBlocker('in_progress', $start, $end, $deadline))
        ->toBe(BlockReason::WINDOW_CLOSED);
});

it('completeBlocker reports not_started for scheduled sessions before their start', function () use ($now): void {
    $start = $now()->addHour();
    $end = $now()->subMinute();

    expect(LifecycleRules::completeBlocker('scheduled', $start, $end, $now()))
        ->toBe(BlockReason::NOT_STARTED);
});

function attendanceAssessFacts(array $session = [], ?array $attendee = null): array
{
    $endAt = CarbonImmutable::parse('2026-10-12T11:00:00Z');

    return [
        'session' => array_merge([
            'status' => 'completed',
            'end_at' => $endAt,
            'excluded_user_ids' => [],
        ], $session),
        'attendee' => $attendee ?? [
            'user_id' => 'member-1',
            'created_at' => $endAt->subHour(),
            'left_at' => null,
            'confirmed_by' => null,
        ],
        'end_at' => $endAt,
    ];
}

function runAssess(array $facts, string $actorId, string $targetId, AttendanceRelation $relation, array $chargeable, CarbonImmutable $now): array
{
    return AttendanceRules::assess(
        $facts['session'],
        $facts['attendee'],
        $actorId,
        $targetId,
        $relation,
        $chargeable,
        $now,
    );
}

it('assess reports not_registered for missing or mismatched attendee', function (): void {
    $facts = attendanceAssessFacts();
    $now = $facts['end_at']->addHour();

    expect(runAssess($facts, 'actor', 'member-1', AttendanceRelation::Responsible, ['member-1'], $now)[0])
        ->toBe(AttendanceDecision::Update);

    $noAttendee = attendanceAssessFacts(attendee: null);
    $noAttendee['attendee'] = null;
    expect(runAssess($noAttendee, 'actor', 'member-1', AttendanceRelation::Responsible, ['member-1'], $now))
        ->toBe([AttendanceDecision::Reject, BlockReason::NOT_REGISTERED]);

    expect(runAssess($facts, 'actor', 'other-user', AttendanceRelation::Responsible, ['other-user'], $now))
        ->toBe([AttendanceDecision::Reject, BlockReason::NOT_REGISTERED]);
});

it('assess reports terminal_status for canceled and not_completed sessions', function (string $status): void {
    $facts = attendanceAssessFacts(['status' => $status]);
    $now = $facts['end_at']->addHour();

    expect(runAssess($facts, 'member-1', 'member-1', AttendanceRelation::Participant, [], $now))
        ->toBe([AttendanceDecision::Reject, BlockReason::TERMINAL_STATUS]);
})->with(['canceled', 'not_completed']);

it('assess reports not_registered for invalid schedule facts or early departure', function (array $session, ?array $attendee): void {
    $facts = attendanceAssessFacts($session, $attendee);
    $now = $facts['end_at']->addHour();

    expect(runAssess($facts, 'member-1', 'member-1', AttendanceRelation::Participant, [], $now))
        ->toBe([AttendanceDecision::Reject, BlockReason::NOT_REGISTERED]);
})->with([
    'no end_at' => [['end_at' => null], null],
    'no created_at' => [[], ['user_id' => 'member-1', 'created_at' => null]],
    'joined after end' => [[], ['user_id' => 'member-1', 'created_at' => CarbonImmutable::parse('2026-10-12T11:00:01Z')]],
    'left at end' => [[], ['user_id' => 'member-1', 'created_at' => CarbonImmutable::parse('2026-10-12T10:00:00Z'), 'left_at' => CarbonImmutable::parse('2026-10-12T11:00:00Z')]],
    'left before end' => [[], ['user_id' => 'member-1', 'created_at' => CarbonImmutable::parse('2026-10-12T10:00:00Z'), 'left_at' => CarbonImmutable::parse('2026-10-12T10:59:59Z')]],
]);

it('assess reports excluded for excluded targets', function (): void {
    $facts = attendanceAssessFacts(['excluded_user_ids' => ['member-1']]);
    $now = $facts['end_at']->addHour();

    expect(runAssess($facts, 'member-1', 'member-1', AttendanceRelation::Participant, [], $now))
        ->toBe([AttendanceDecision::Reject, BlockReason::EXCLUDED]);
});

it('assess reports not_ended and window_closed at the window boundaries', function (): void {
    $facts = attendanceAssessFacts();
    $end = $facts['end_at'];
    $deadline = $end->addHours(48);

    expect(runAssess($facts, 'member-1', 'member-1', AttendanceRelation::Participant, [], $end->subSecond()))
        ->toBe([AttendanceDecision::Reject, BlockReason::NOT_ENDED]);
    expect(runAssess($facts, 'member-1', 'member-1', AttendanceRelation::Participant, [], $deadline->subSecond())[0])
        ->toBe(AttendanceDecision::Update);
    expect(runAssess($facts, 'member-1', 'member-1', AttendanceRelation::Participant, [], $deadline))
        ->toBe([AttendanceDecision::Reject, BlockReason::WINDOW_CLOSED]);
});

it('assess lets the member confirm themselves inside the window', function (): void {
    $facts = attendanceAssessFacts();

    expect(runAssess($facts, 'member-1', 'member-1', AttendanceRelation::Participant, [], $facts['end_at']->addHour()))
        ->toBe([AttendanceDecision::Update, null]);
});

it('assess reports forbidden for a mentor acting on another member', function (): void {
    $facts = attendanceAssessFacts();

    expect(runAssess($facts, 'mentor-1', 'member-1', AttendanceRelation::Mentor, ['member-1'], $facts['end_at']->addHour()))
        ->toBe([AttendanceDecision::Reject, BlockReason::FORBIDDEN]);
});

it('assess reports forbidden for non-responsible actors and not_chargeable for non-chargeable targets', function (): void {
    $facts = attendanceAssessFacts();
    $now = $facts['end_at']->addHour();

    expect(runAssess($facts, 'actor', 'member-1', AttendanceRelation::Other, ['member-1'], $now))
        ->toBe([AttendanceDecision::Reject, BlockReason::FORBIDDEN]);
    expect(runAssess($facts, 'actor', 'member-1', AttendanceRelation::Responsible, ['someone-else'], $now))
        ->toBe([AttendanceDecision::Reject, BlockReason::NOT_CHARGEABLE]);
});

it('assess keeps an already member-confirmed record with the reason', function (): void {
    $facts = attendanceAssessFacts([], [
        'user_id' => 'member-1',
        'created_at' => CarbonImmutable::parse('2026-10-12T10:00:00Z'),
        'left_at' => null,
        'confirmed_by' => 'member-1',
    ]);

    expect(runAssess($facts, 'responsible-1', 'member-1', AttendanceRelation::Responsible, ['member-1'], $facts['end_at']->addHour()))
        ->toBe([AttendanceDecision::Keep, BlockReason::ALREADY_CONFIRMED_BY_MEMBER]);
});

it('assess updates a responsible actor on a chargeable target', function (): void {
    $facts = attendanceAssessFacts();

    expect(runAssess($facts, 'responsible-1', 'member-1', AttendanceRelation::Responsible, ['member-1'], $facts['end_at']->addHour()))
        ->toBe([AttendanceDecision::Update, null]);
});

it('evaluate returns the same decision as assess[0] over a grid of inputs', function (
    ?array $attendee,
    string $status,
    string $actorId,
    string $targetId,
    AttendanceRelation $relation,
    array $chargeable,
    int $nowOffsetMinutes,
): void {
    $facts = attendanceAssessFacts(['status' => $status], $attendee);
    $now = $facts['end_at']->addMinutes($nowOffsetMinutes);

    $assess = AttendanceRules::assess($facts['session'], $facts['attendee'], $actorId, $targetId, $relation, $chargeable, $now);
    $evaluate = AttendanceRules::evaluate($facts['session'], $facts['attendee'], $actorId, $targetId, $relation, $chargeable, $now);

    expect($evaluate)->toBe($assess[0]);
})->with([
    'member self update' => [null, 'completed', 'member-1', 'member-1', AttendanceRelation::Participant, [], 60],
    'responsible update' => [null, 'completed', 'resp', 'member-1', AttendanceRelation::Responsible, ['member-1'], 60],
    'mentor reject' => [null, 'completed', 'mentor', 'member-1', AttendanceRelation::Mentor, ['member-1'], 60],
    'not chargeable' => [null, 'completed', 'resp', 'member-1', AttendanceRelation::Responsible, [], 60],
    'canceled session' => [null, 'canceled', 'member-1', 'member-1', AttendanceRelation::Participant, [], 60],
    'before end' => [null, 'completed', 'member-1', 'member-1', AttendanceRelation::Participant, [], -1],
    'after window' => [null, 'completed', 'member-1', 'member-1', AttendanceRelation::Participant, [], 2880],
    'no attendee' => [['user_id' => 'other', 'created_at' => CarbonImmutable::parse('2026-10-12T10:00:00Z')], 'completed', 'actor', 'member-1', AttendanceRelation::Responsible, ['member-1'], 60],
]);
