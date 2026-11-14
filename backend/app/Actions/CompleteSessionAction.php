<?php

namespace App\Actions;

use App\Enums\AttendanceStatus;
use App\Enums\SessionAttendanceEventType;
use App\Enums\SessionStatus;
use App\Events\SessionLifecycleTransitioned;
use App\Models\Session;
use App\Models\SessionAttendanceEvent;
use App\Models\SessionAttendee;
use App\Models\User;
use App\Services\Scheduling\Rules\BlockReason;
use App\Services\Scheduling\Rules\LifecycleRules;
use App\Services\Scheduling\Rules\ParticipantRules;
use App\Support\DbTime;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteSessionAction
{
    public function execute(string $sessionId, User $actor, DateTimeInterface $now): Session
    {
        $transitions = DB::transaction(function () use ($sessionId, $actor, $now): array {
            $session = Session::query()->whereKey($sessionId)->lockForUpdate()->first();

            if (! $session) {
                throw (new ModelNotFoundException())->setModel(Session::class, [$sessionId]);
            }

            $relationship = $session->relationship()->first();
            if (! $relationship) {
                throw (new ModelNotFoundException())->setModel(Session::class, [$sessionId]);
            }

            if ($relationship->mentor_id !== $actor->id) {
                throw new AuthorizationException(__('sessions.complete_forbidden'));
            }

            $attributes = $session->getAttributes();
            $startAt = $attributes['scheduled_at'] === null
                ? null
                : DbTime::fromRaw($attributes['scheduled_at']);
            $endAt = $attributes['end_at'] === null
                ? null
                : DbTime::fromRaw($attributes['end_at']);
            $occurredAt = CarbonImmutable::instance($now)->setTimezone('UTC');

            $blocker = LifecycleRules::completeBlocker(
                $session->status,
                $startAt,
                $endAt,
                $occurredAt,
                (int) config('scheduling.not_completed_after_hours', 48),
            );

            if ($blocker !== null) {
                $this->reject(match ($blocker) {
                    BlockReason::TERMINAL_STATUS => __('sessions.complete_terminal'),
                    BlockReason::INVALID_SCHEDULE => __('sessions.complete_invalid_schedule'),
                    BlockReason::NOT_ENDED => __('sessions.complete_not_ended'),
                    BlockReason::WINDOW_CLOSED => __('sessions.complete_window_closed'),
                    default => __('sessions.complete_not_started'),
                });
            }

            $transitions = [];
            if ($session->status === SessionStatus::Scheduled->value) {
                if ($occurredAt < $startAt) {
                    $this->reject(__('sessions.complete_not_started'));
                }

                $transitions[] = $this->transition($session, SessionStatus::InProgress, $actor, $occurredAt);
            }

            $previousStatus = $session->status;
            $session->forceFill([
                'status' => SessionStatus::Completed->value,
                'completed_at' => DbTime::toRaw($occurredAt),
                'completed_by' => $actor->id,
            ])->save();
            $this->recordTransition($session, $previousStatus, SessionStatus::Completed, $actor, $occurredAt);
            $transitions[] = [
                'session_id' => (string) $session->id,
                'relationship_id' => (string) $session->relationship_id,
                'previous_status' => $previousStatus,
                'new_status' => SessionStatus::Completed->value,
                'occurred_at' => $occurredAt->toIso8601String(),
            ];

            $this->ensureAttendanceInitializations($session, $relationship->mentor_id, $endAt, $occurredAt);

            return $transitions;
        });

        foreach ($transitions as $transition) {
            event(new SessionLifecycleTransitioned(
                $transition['session_id'],
                $transition['relationship_id'],
                $transition['previous_status'],
                $transition['new_status'],
                $transition['occurred_at'],
            ));
        }

        return Session::query()->findOrFail($sessionId);
    }

    private function transition(
        Session $session,
        SessionStatus $target,
        User $actor,
        CarbonImmutable $occurredAt,
    ): array {
        $previousStatus = $session->status;
        $session->forceFill(['status' => $target->value])->save();
        $this->recordTransition($session, $previousStatus, $target, $actor, $occurredAt);

        return [
            'session_id' => (string) $session->id,
            'relationship_id' => (string) $session->relationship_id,
            'previous_status' => $previousStatus,
            'new_status' => $target->value,
            'occurred_at' => $occurredAt->toIso8601String(),
        ];
    }

    private function recordTransition(
        Session $session,
        string $previousStatus,
        SessionStatus $target,
        User $actor,
        CarbonImmutable $occurredAt,
    ): void {
        activity('scheduling')
            ->causedBy($actor)
            ->performedOn($session)
            ->event('mentor.session.status_transition')
            ->withProperties([
                'session_id' => $session->id,
                'actor' => (string) $actor->id,
                'changed_fields' => ['status'],
                'before' => ['status' => $previousStatus],
                'after' => ['status' => $target->value],
                'occurred_at' => $occurredAt->toIso8601String(),
            ])
            ->log('mentor.session.status_transition');
    }

    private function ensureAttendanceInitializations(
        Session $session,
        string $mentorId,
        CarbonImmutable $endAt,
        CarbonImmutable $occurredAt,
    ): void {
        $attendees = $session->attendees()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $exclusions = $session->participantExclusions()
            ->pluck('user_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
        $participantIds = ParticipantRules::participantsAtEnd(
            [
                'mentor_id' => $mentorId,
                'excluded_user_ids' => $exclusions,
            ],
            $attendees->map(static function (SessionAttendee $attendee): array {
                $attributes = $attendee->getAttributes();

                return [
                    'user_id' => (string) $attendee->user_id,
                    'created_at' => DbTime::fromRaw($attributes['created_at']),
                    'left_at' => $attributes['left_at'] === null
                        ? null
                        : DbTime::fromRaw($attributes['left_at']),
                ];
            })->all(),
            $endAt,
            $occurredAt,
        );

        foreach ($participantIds as $userId) {
            $attendee = $session->attendees()->firstOrCreate(['user_id' => $userId]);
            $alreadyInitialized = $attendee->attendanceEvents()
                ->where('event_type', SessionAttendanceEventType::Initialization->value)
                ->exists();

            if ($alreadyInitialized) {
                continue;
            }

            SessionAttendanceEvent::query()->create([
                'session_attendee_id' => $attendee->id,
                'event_type' => SessionAttendanceEventType::Initialization,
                'attendance_status' => AttendanceStatus::NotConfirmed,
                'recorded_by' => null,
                'occurred_at' => DbTime::toRaw($endAt),
            ]);
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['session' => $message]);
    }
}
