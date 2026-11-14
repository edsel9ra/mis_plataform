<?php

namespace App\Services\Scheduling;

use App\Enums\AttendanceDecision;
use App\Enums\AttendanceRelation;
use App\Models\Session;
use App\Models\User;
use App\Services\Scheduling\Rules\AttendanceRules;
use App\Services\Scheduling\Rules\BlockReason;
use App\Services\Scheduling\Rules\ChangeRules;
use App\Services\Scheduling\Rules\LifecycleRules;
use App\Support\DbTime;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;

/**
 * Computes which session-detail actions a user may take, using the same
 * pure rules the actions enforce. Read-only; callers inject `now`.
 */
final class SessionActionAvailability
{
    public function __construct(
        private AttendanceEligibility $eligibility = new AttendanceEligibility(),
    ) {}

    public function for(Session $session, User $user, CarbonInterface $now): array
    {
        $manage = Gate::forUser($user)->inspect('manage', $session);
        $scheduledAt = Carbon::parse(
            $session->getRawOriginal('scheduled_at'),
            (string) config('app.timezone'),
        );

        $rescheduleReason = $manage->denied()
            ? BlockReason::FORBIDDEN
            : ChangeRules::rescheduleBlocker($session->status, $scheduledAt, $now);

        $cancelReason = $manage->denied()
            ? BlockReason::FORBIDDEN
            : ChangeRules::cancelBlocker($session->status);

        $relationship = $session->relationship()->first();
        $attributes = $session->getAttributes();
        $occurredAt = CarbonImmutable::instance($now)->setTimezone('UTC');

        if (! $relationship || $relationship->mentor_id !== $user->id) {
            $completeReason = BlockReason::FORBIDDEN;
        } else {
            $completeReason = LifecycleRules::completeBlocker(
                $session->status,
                $attributes['scheduled_at'] === null
                    ? null
                    : DbTime::fromRaw($attributes['scheduled_at']),
                $attributes['end_at'] === null
                    ? null
                    : DbTime::fromRaw($attributes['end_at']),
                $occurredAt,
                (int) config('scheduling.not_completed_after_hours', 48),
            );
        }

        return [
            'reschedule' => ['allowed' => $rescheduleReason === null, 'reason' => $rescheduleReason],
            'cancel' => [
                'allowed' => $cancelReason === null,
                'reason' => $cancelReason,
                'late' => $cancelReason === null && ChangeRules::isLate($scheduledAt, $now),
            ],
            'complete' => ['allowed' => $completeReason === null, 'reason' => $completeReason],
            'attendance' => $this->attendance($session, $relationship, $user, $occurredAt),
        ];
    }

    private function attendance($session, $relationship, User $user, CarbonImmutable $occurredAt): array
    {
        if (! $relationship) {
            return [];
        }

        $attendees = $session->attendees;
        $selfAttendee = $attendees->firstWhere('user_id', $user->id);
        $entries = [];

        $isResponsible = $this->eligibility->actorRelation(
            $user,
            (string) $relationship->mentor_id,
            $relationship,
        ) === AttendanceRelation::Responsible;
        $chargeableTargetIds = $isResponsible
            ? $this->eligibility->chargeableTargetIds($relationship, $session)
            : [];

        $targets = [];
        if ($selfAttendee) {
            $targets[] = ['attendee' => $selfAttendee, 'self' => true, 'chargeable' => []];
        }
        foreach ($chargeableTargetIds as $targetId) {
            if ($targetId === (string) $user->id) {
                continue;
            }
            $attendee = $attendees->firstWhere('user_id', $targetId);
            if ($attendee) {
                $targets[] = ['attendee' => $attendee, 'self' => false, 'chargeable' => $chargeableTargetIds];
            }
        }

        if ($targets === []) {
            return [];
        }

        $attributes = $session->getAttributes();
        $excludedUserIds = $session->participantExclusions()
            ->pluck('user_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
        $sessionFacts = [
            'status' => (string) $attributes['status'],
            'end_at' => DbTime::fromRaw($attributes['end_at']),
            'excluded_user_ids' => $excludedUserIds,
        ];
        $windowHours = (int) config('scheduling.attendance_window_hours', 48);

        foreach ($targets as $target) {
            $attendeeAttributes = $target['attendee']->getAttributes();
            $actorRelation = $target['self']
                ? AttendanceRelation::Participant
                : AttendanceRelation::Responsible;
            [$decision, $reason] = AttendanceRules::assess(
                $sessionFacts,
                [
                    'user_id' => (string) $attendeeAttributes['user_id'],
                    'created_at' => DbTime::fromRaw($attendeeAttributes['created_at']),
                    'left_at' => $attendeeAttributes['left_at'] === null
                        ? null
                        : DbTime::fromRaw($attendeeAttributes['left_at']),
                    'confirmed_by' => $attendeeAttributes['confirmed_by'],
                ],
                (string) $user->id,
                (string) $attendeeAttributes['user_id'],
                $actorRelation,
                $target['chargeable'],
                $occurredAt,
                $windowHours,
            );

            $entries[] = [
                'user_id' => (string) $attendeeAttributes['user_id'],
                'self' => $target['self'],
                'allowed' => $decision === AttendanceDecision::Update,
                'reason' => $decision === AttendanceDecision::Update ? null : $reason,
                'current_status' => $attendeeAttributes['attendance_status'] ?? 'not_confirmed',
            ];
        }

        return $entries;
    }
}
