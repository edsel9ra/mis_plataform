<?php

use App\Enums\AttendanceDecision;
use App\Enums\AttendanceRelation;
use App\Services\Scheduling\Rules\AttendanceRules;
use App\Services\Scheduling\Rules\ParticipantRules;
use Carbon\CarbonImmutable;

function attendanceFacts(array $session = [], ?array $attendee = null): array
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

it('opens the confirmation window at the scheduled end and allows confirmation before the 48 hour deadline', function (): void {
    $facts = attendanceFacts();

    expect(AttendanceRules::evaluate(
        $facts['session'],
        $facts['attendee'],
        'member-1',
        'member-1',
        AttendanceRelation::Participant,
        [],
        $facts['end_at'],
    ))->toBe(AttendanceDecision::Update)
        ->and(AttendanceRules::evaluate(
            $facts['session'],
            $facts['attendee'],
            'member-1',
            'member-1',
            AttendanceRelation::Participant,
            [],
            $facts['end_at']->addHours(47)->addMinutes(59)->addSeconds(59),
        ))->toBe(AttendanceDecision::Update);
});

it('closes the confirmation window exactly at end plus 48 hours', function (): void {
    $facts = attendanceFacts();

    expect(AttendanceRules::evaluate(
        $facts['session'],
        $facts['attendee'],
        'member-1',
        'member-1',
        AttendanceRelation::Participant,
        [],
        $facts['end_at']->addHours(48),
    ))->toBe(AttendanceDecision::Reject);
});

it('rejects early, canceled, not-completed, and missing-attendee confirmations', function (): void {
    $facts = attendanceFacts();
    $canConfirm = fn (array $session, ?array $attendee, CarbonImmutable $now) => AttendanceRules::evaluate(
        $session,
        $attendee,
        'member-1',
        'member-1',
        AttendanceRelation::Participant,
        [],
        $now,
    );

    expect($canConfirm($facts['session'], $facts['attendee'], $facts['end_at']->subSecond()))
        ->toBe(AttendanceDecision::Reject)
        ->and($canConfirm(array_merge($facts['session'], ['status' => 'canceled']), $facts['attendee'], $facts['end_at']))
        ->toBe(AttendanceDecision::Reject)
        ->and($canConfirm(array_merge($facts['session'], ['status' => 'not_completed']), $facts['attendee'], $facts['end_at']))
        ->toBe(AttendanceDecision::Reject)
        ->and($canConfirm($facts['session'], null, $facts['end_at']))
        ->toBe(AttendanceDecision::Reject);
});

it('rejects attendees outside the end-time participant anchor and case 41 exclusions', function (): void {
    $facts = attendanceFacts();
    $joinedAfterEnd = array_merge($facts['attendee'], [
        'created_at' => $facts['end_at']->addSecond(),
    ]);
    $leftAtEnd = array_merge($facts['attendee'], [
        'left_at' => $facts['end_at'],
    ]);

    $evaluate = fn (array $session, array $attendee) => AttendanceRules::evaluate(
        $session,
        $attendee,
        'member-1',
        'member-1',
        AttendanceRelation::Participant,
        [],
        $facts['end_at'],
    );

    expect($evaluate($facts['session'], $joinedAfterEnd))->toBe(AttendanceDecision::Reject)
        ->and($evaluate($facts['session'], $leftAtEnd))->toBe(AttendanceDecision::Reject)
        ->and($evaluate(
            array_merge($facts['session'], ['excluded_user_ids' => ['member-1']]),
            $facts['attendee'],
        ))->toBe(AttendanceDecision::Reject);
});

it('allows a responsible to confirm only targets in the chargeable set and never lets a mentor confirm for another', function (): void {
    $facts = attendanceFacts();
    $evaluate = fn (AttendanceRelation $relation, array $eligibleIds) => AttendanceRules::evaluate(
        $facts['session'],
        $facts['attendee'],
        'actor-1',
        'member-1',
        $relation,
        $eligibleIds,
        $facts['end_at'],
    );

    expect($evaluate(AttendanceRelation::Responsible, ['member-1']))
        ->toBe(AttendanceDecision::Update)
        ->and($evaluate(AttendanceRelation::Responsible, []))
        ->toBe(AttendanceDecision::Reject)
        ->and($evaluate(AttendanceRelation::Mentor, ['member-1']))
        ->toBe(AttendanceDecision::Reject)
        ->and($evaluate(AttendanceRelation::Other, ['member-1']))
        ->toBe(AttendanceDecision::Reject);
});

it('allows mentors and responsible users to confirm their own attendance', function (): void {
    $facts = attendanceFacts();
    $attendee = array_merge($facts['attendee'], ['user_id' => 'actor-1']);

    expect(AttendanceRules::evaluate(
        $facts['session'],
        $attendee,
        'actor-1',
        'actor-1',
        AttendanceRelation::Mentor,
        [],
        $facts['end_at'],
    ))->toBe(AttendanceDecision::Update)
        ->and(AttendanceRules::evaluate(
            $facts['session'],
            $attendee,
            'actor-1',
            'actor-1',
            AttendanceRelation::Responsible,
            [],
            $facts['end_at'],
        ))->toBe(AttendanceDecision::Update);
});

it('keeps the member confirmation when the responsible confirms afterward and lets the member replace an earlier responsible confirmation', function (): void {
    $facts = attendanceFacts();
    $evaluate = fn (string $confirmedBy, string $actorId, string $targetId, AttendanceRelation $relation) => AttendanceRules::evaluate(
        $facts['session'],
        array_merge($facts['attendee'], ['confirmed_by' => $confirmedBy]),
        $actorId,
        $targetId,
        $relation,
        ['member-1'],
        $facts['end_at'],
    );

    expect($evaluate('member-1', 'responsible-1', 'member-1', AttendanceRelation::Responsible))
        ->toBe(AttendanceDecision::Keep)
        ->and($evaluate('responsible-1', 'member-1', 'member-1', AttendanceRelation::Participant))
        ->toBe(AttendanceDecision::Update);
});

it('calculates chargeable family members and cohort minors conservatively, and company employees by membership', function (): void {
    $members = ['minor' => 'minor', 'adult' => 'adult', 'unknown' => 'unknown', 'employee' => 'employee'];
    $ages = ['minor' => 17, 'adult' => 18, 'employee' => 35];

    expect(ParticipantRules::chargeableMembers('family_group', array_values($members), $ages))
        ->toBe(['minor'])
        ->and(ParticipantRules::chargeableMembers('cohort', array_values($members), $ages))
        ->toBe(['minor'])
        ->and(ParticipantRules::chargeableMembers('company', ['employee'], []))
        ->toBe(['employee']);
});
