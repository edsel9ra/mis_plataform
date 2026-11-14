<?php

namespace App\Actions;

use App\Events\SessionRescheduled;
use App\Models\Session;
use App\Models\SessionScheduleChange;
use App\Models\User;
use App\Services\Scheduling\Rules\BlockReason;
use App\Services\Scheduling\Rules\ChangeRules;
use App\Services\Scheduling\SessionBookingService;
use App\Services\Scheduling\SchedulingAuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RescheduleSessionAction
{
    public function __construct(
        private SessionBookingService $sessionBookingService,
        private SchedulingAuditLogger $auditLogger,
    ) {}

    public function execute(string $sessionId, array $data, User $actor, Carbon $now): Session
    {
        /** @var SessionScheduleChange|null $change */
        $change = null;
        $updatedSession = DB::transaction(function () use ($sessionId, $data, $actor, $now, &$change): Session {
            $session = $this->sessionBookingService->lockSessionForReschedule($sessionId);
            Gate::forUser($actor)->authorize('manage', $session);
            $existingStart = Carbon::parse(
                $session->getRawOriginal('scheduled_at'),
                (string) config('app.timezone'),
            );

            $blocker = ChangeRules::rescheduleBlocker($session->status, $existingStart, $now);
            if ($blocker !== null) {
                $field = $blocker === BlockReason::INSUFFICIENT_NOTICE
                    ? 'reschedule_notice'
                    : 'reschedule_not_eligible';

                throw ValidationException::withMessages([
                    'scheduled_at' => [__('sessions.'.$field)],
                ]);
            }

            $candidate = $this->sessionBookingService->validateReschedule($session, $data, $actor, $now);
            $before = $this->auditLogger->snapshot($session);
            $scheduledAtBefore = $existingStart;
            $timezoneBefore = $session->timezone;
            $durationBefore = (int) $session->duration_minutes;
            $occurredAt = $now->copy()->setTimezone((string) config('app.timezone'));

            $session->scheduled_at = $candidate['scheduled_at'];
            $session->timezone = $candidate['timezone'];
            $session->duration_minutes = $candidate['duration_minutes'];
            $session->save();

            $change = SessionScheduleChange::query()->create([
                'session_id' => $session->id,
                'scheduled_at_before' => $scheduledAtBefore,
                'scheduled_at_after' => $candidate['scheduled_at'],
                'timezone_before' => $timezoneBefore,
                'timezone_after' => $candidate['timezone'],
                'duration_minutes_before' => $durationBefore,
                'duration_minutes_after' => $candidate['duration_minutes'],
                'actor_id' => $actor->id,
                'occurred_at' => $occurredAt,
                'reason' => $data['reason'] ?? null,
            ]);

            $session->load(['relationship.mentor', 'attendees.user']);
            $this->auditLogger->record(
                $session,
                $actor,
                'session.rescheduled',
                ['scheduled_at', 'timezone', 'duration_minutes'],
                $before,
            );

            return $session;
        });

        // The event ID comes from the append-only history row, giving retries
        // and downstream consumers one stable identifier for this change.
        SessionRescheduled::dispatch(
            eventId: $change->id,
            sessionId: $updatedSession->id,
            occurredAt: $change->occurred_at->toIso8601String(),
            previousSchedule: [
                'scheduled_at' => $change->scheduled_at_before->toIso8601String(),
                'timezone' => $change->timezone_before,
                'duration_minutes' => $change->duration_minutes_before,
            ],
            newSchedule: array_filter([
                'scheduled_at' => $change->scheduled_at_after->toIso8601String(),
                'timezone' => $change->timezone_after,
                'duration_minutes' => $change->duration_minutes_after,
                'reason' => $change->reason,
            ], static fn (mixed $value): bool => $value !== null),
        );

        return $updatedSession;
    }
}
