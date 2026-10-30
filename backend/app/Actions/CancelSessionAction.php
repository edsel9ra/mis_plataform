<?php

namespace App\Actions;

use App\Enums\SessionStatus;
use App\Events\SessionCanceled;
use App\Models\Session;
use App\Models\User;
use App\Services\Scheduling\Rules\ChangeRules;
use App\Services\Scheduling\SessionBookingService;
use App\Services\Scheduling\SchedulingAuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancelSessionAction
{
    public function __construct(
        private SessionBookingService $sessionBookingService,
        private SchedulingAuditLogger $auditLogger,
    ) {}

    public function execute(string $sessionId, ?string $reason, User $actor, Carbon $now): Session
    {
        if ($reason === null || trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => [__('sessions.cancellation_reason_required')],
            ]);
        }

        $canceledSession = DB::transaction(function () use ($sessionId, $reason, $actor, $now): Session {
            $session = $this->sessionBookingService->lockSessionForReschedule($sessionId);

            Gate::forUser($actor)->authorize('manage', $session);

            if (! in_array($session->status, [SessionStatus::Scheduled->value, SessionStatus::InProgress->value], true)) {
                throw ValidationException::withMessages([
                    'status' => [__('sessions.cancellation_not_eligible')],
                ]);
            }

            $scheduledAt = Carbon::parse(
                $session->getRawOriginal('scheduled_at'),
                (string) config('app.timezone'),
            );
            $before = $this->auditLogger->snapshot($session);
            $session->forceFill([
                'status' => SessionStatus::Canceled->value,
                'canceled_at' => $now->copy()->setTimezone((string) config('app.timezone')),
                'canceled_by' => $actor->id,
                'cancellation_reason' => $reason,
                'canceled_late' => ChangeRules::isLate($scheduledAt, $now),
                'cancellation_lead_minutes' => ChangeRules::cancellationLead($scheduledAt, $now),
            ])->save();

            $session->load(['relationship.mentor', 'attendees.user']);
            $this->auditLogger->record(
                $session,
                $actor,
                'session.canceled',
                ['status', 'canceled_at', 'canceled_by', 'cancellation_reason', 'canceled_late', 'cancellation_lead_minutes'],
                $before,
            );

            return $session;
        });

        SessionCanceled::dispatch(
            eventId: $canceledSession->id,
            sessionId: $canceledSession->id,
            actorId: $actor->id,
            occurredAt: $canceledSession->canceled_at->toIso8601String(),
            reason: $canceledSession->cancellation_reason,
            late: $canceledSession->canceled_late,
            leadMinutes: $canceledSession->cancellation_lead_minutes,
        );

        return $canceledSession;
    }
}
