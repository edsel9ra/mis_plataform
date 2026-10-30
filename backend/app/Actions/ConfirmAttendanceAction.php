<?php

namespace App\Actions;

use App\Enums\AttendanceDecision;
use App\Enums\AttendanceRelation;
use App\Enums\AttendanceStatus;
use App\Enums\SessionAttendanceEventType;
use App\Models\Employee;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendanceEvent;
use App\Models\SessionAttendee;
use App\Models\User;
use App\Services\Scheduling\Rules\AttendanceRules;
use App\Services\Scheduling\Rules\ParticipantRules;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmAttendanceAction
{
    public function execute(
        string $sessionId,
        User $actor,
        AttendanceStatus $status,
        ?string $targetUserId,
        DateTimeInterface $now,
    ): SessionAttendee {
        return DB::transaction(function () use ($sessionId, $actor, $status, $targetUserId, $now): SessionAttendee {
            $session = Session::query()
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw (new ModelNotFoundException())->setModel(Session::class, [$sessionId]);
            }

            $relationship = $session->relationship()->first();

            if (! $relationship) {
                throw (new ModelNotFoundException())->setModel(Session::class, [$sessionId]);
            }

            $targetUserId ??= (string) $actor->id;
            $attendee = SessionAttendee::query()
                ->where('session_id', $session->id)
                ->where('user_id', $targetUserId)
                ->lockForUpdate()
                ->first();

            if (! $attendee) {
                throw new AuthorizationException(__('sessions.attendee_not_registered'));
            }

            $actorRelation = $this->actorRelation($actor, $targetUserId, $relationship);
            $chargeableTargetIds = [];

            if ($targetUserId !== (string) $actor->id) {
                if ($actorRelation !== AttendanceRelation::Responsible) {
                    throw new AuthorizationException(__('sessions.attendance_confirmation_denied'));
                }

                $chargeableTargetIds = $this->chargeableTargetIds($relationship, $session);

                if (! in_array($targetUserId, $chargeableTargetIds, true)) {
                    throw new AuthorizationException(__('sessions.attendance_target_not_chargeable'));
                }
            }

            $sessionAttributes = $session->getAttributes();
            $attendeeAttributes = $attendee->getAttributes();
            $excludedUserIds = $session->participantExclusions()
                ->pluck('user_id')
                ->map(static fn ($id): string => (string) $id)
                ->all();
            $occurredAt = CarbonImmutable::instance($now)->setTimezone('UTC');
            $decision = AttendanceRules::evaluate(
                [
                    'status' => (string) $sessionAttributes['status'],
                    'end_at' => $sessionAttributes['end_at'],
                    'excluded_user_ids' => $excludedUserIds,
                ],
                [
                    'user_id' => (string) $attendeeAttributes['user_id'],
                    'created_at' => $attendeeAttributes['created_at'],
                    'left_at' => $attendeeAttributes['left_at'],
                    'confirmed_by' => $attendeeAttributes['confirmed_by'],
                ],
                (string) $actor->id,
                $targetUserId,
                $actorRelation,
                $chargeableTargetIds,
                $occurredAt,
                (int) config('scheduling.attendance_window_hours', 48),
            );

            if ($decision === AttendanceDecision::Reject) {
                throw ValidationException::withMessages([
                    'attendance' => __('sessions.attendance_confirmation_unavailable'),
                ]);
            }

            SessionAttendanceEvent::query()->create([
                'session_attendee_id' => $attendee->id,
                'event_type' => SessionAttendanceEventType::Confirmation,
                'attendance_status' => $status,
                'recorded_by' => $actor->id,
                'occurred_at' => $occurredAt,
            ]);

            if ($decision === AttendanceDecision::Update) {
                $attendee->forceFill([
                    'attendance_status' => $status,
                    'attended' => $status === AttendanceStatus::Attended,
                    'confirmed_by' => $actor->id,
                    'confirmed_at' => $occurredAt,
                ])->save();
            }

            if ($targetUserId !== (string) $actor->id) {
                activity('scheduling')
                    ->causedBy($actor)
                    ->performedOn($session)
                    ->event('session.attendance_confirmed_for_participant')
                    ->withProperties([
                        'session_id' => $session->id,
                        'attendee_user_id' => $targetUserId,
                        'attendance_status' => $status->value,
                        'occurred_at' => $occurredAt->toIso8601String(),
                    ])
                    ->log('session.attendance_confirmed_for_participant');
            }

            return $attendee->fresh();
        });
    }

    private function actorRelation(User $actor, string $targetUserId, MentorshipRelationship $relationship): AttendanceRelation
    {
        if ((string) $actor->id === $targetUserId) {
            return AttendanceRelation::Participant;
        }

        if ($actor->isAdmin()) {
            return AttendanceRelation::Other;
        }

        if ($relationship->mentor_id === $actor->id) {
            return AttendanceRelation::Mentor;
        }

        $sourceType = MentorshipRelationship::canonicalSourceType($relationship->source_type);

        if (in_array($sourceType, ['family_group', 'cohort', 'company'], true)
            && MentorshipRelationship::userCanManageSource($actor, $sourceType, $relationship->source_id)) {
            return AttendanceRelation::Responsible;
        }

        return AttendanceRelation::Other;
    }

    /** @return list<string> */
    private function chargeableTargetIds(MentorshipRelationship $relationship, Session $session): array
    {
        $sourceType = MentorshipRelationship::canonicalSourceType($relationship->source_type);
        $attendeeIds = $session->attendees()
            ->pluck('user_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        if ($sourceType === 'company') {
            $employeeIds = Employee::query()
                ->where('company_id', $relationship->source_id)
                ->whereIn('user_id', $attendeeIds)
                ->pluck('user_id')
                ->map(static fn ($id): string => (string) $id)
                ->all();

            return ParticipantRules::chargeableMembers($sourceType, $employeeIds, []);
        }

        $members = match ($sourceType) {
            'family_group' => FamilyMember::query()
                ->where('family_group_id', $relationship->source_id)
                ->whereIn('user_id', $attendeeIds)
                ->orderBy('id')
                ->get(['user_id', 'age']),
            'cohort' => FamilyMember::query()
                ->whereIn('user_id', $attendeeIds)
                ->orderBy('id')
                ->get(['user_id', 'age']),
            default => collect(),
        };
        $agesByUserId = [];

        foreach ($members as $member) {
            $agesByUserId[(string) $member->user_id][] = $member->age;
        }

        $ageByUserId = [];
        foreach ($agesByUserId as $userId => $ages) {
            $knownAges = array_values(array_unique(array_filter(
                $ages,
                static fn ($age): bool => is_int($age)
                    ? $age >= 0
                    : is_string($age) && ctype_digit($age),
            )));
            $ageByUserId[$userId] = count($knownAges) === 1 ? $knownAges[0] : null;
        }

        return ParticipantRules::chargeableMembers($sourceType, $attendeeIds, $ageByUserId);
    }
}
