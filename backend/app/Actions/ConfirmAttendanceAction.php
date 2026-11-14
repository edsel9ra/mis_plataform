<?php

namespace App\Actions;

use App\Enums\AttendanceDecision;
use App\Enums\AttendanceRelation;
use App\Enums\AttendanceStatus;
use App\Enums\SessionAttendanceEventType;
use App\Models\Session;
use App\Models\SessionAttendanceEvent;
use App\Models\SessionAttendee;
use App\Models\User;
use App\Services\Scheduling\AttendanceEligibility;
use App\Support\DbTime;
use App\Services\Scheduling\Rules\AttendanceRules;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmAttendanceAction
{
    public function __construct(
        private AttendanceEligibility $eligibility = new AttendanceEligibility(),
    ) {}

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

            $actorRelation = $this->eligibility->actorRelation($actor, $targetUserId, $relationship);
            $chargeableTargetIds = [];

            if ($targetUserId !== (string) $actor->id) {
                if ($actorRelation !== AttendanceRelation::Responsible) {
                    throw new AuthorizationException(__('sessions.attendance_confirmation_denied'));
                }

                $chargeableTargetIds = $this->eligibility->chargeableTargetIds($relationship, $session);

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
                    'end_at' => DbTime::fromRaw($sessionAttributes['end_at']),
                    'excluded_user_ids' => $excludedUserIds,
                ],
                [
                    'user_id' => (string) $attendeeAttributes['user_id'],
                    'created_at' => DbTime::fromRaw($attendeeAttributes['created_at']),
                    'left_at' => $attendeeAttributes['left_at'] === null
                        ? null
                        : DbTime::fromRaw($attendeeAttributes['left_at']),
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
                'occurred_at' => DbTime::toRaw($occurredAt),
            ]);

            if ($decision === AttendanceDecision::Update) {
                $attendee->forceFill([
                    'attendance_status' => $status,
                    'attended' => $status === AttendanceStatus::Attended,
                    'confirmed_by' => $actor->id,
                    'confirmed_at' => DbTime::toRaw($occurredAt),
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
}
