<?php

namespace App\Services\Scheduling;

use App\Models\Session;
use App\Services\GoogleMeetService;
use App\Services\Scheduling\Rules\MeetAutomationRules;
use App\Support\DbTime;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MeetAutomationService
{
    public function __construct(private readonly GoogleMeetService $googleMeetService) {}

    public function synchronizeRescheduled(string $sessionId, DateTimeInterface $now): void
    {
        DB::transaction(function () use ($sessionId, $now): void {
            $session = Session::query()->whereKey($sessionId)->lockForUpdate()->first();
            if (! $session || $session->status !== 'scheduled') {
                return;
            }

            $scheduledAt = $session->getAttributes()['scheduled_at'] ?? null;
            if ($scheduledAt === null || ! DbTime::fromRaw($scheduledAt)
                ->greaterThan(CarbonImmutable::instance($now)->utc())) {
                return;
            }

            // A stored event ID is the only safe address for an update. When
            // there is no associated event at all, create one for the new schedule.
            if ($session->meet_event_id === null && $session->meet_link !== null) {
                return;
            }

            try {
                $start = DbTime::fromRaw($scheduledAt);
                $meetData = $session->meet_event_id === null
                    ? $this->googleMeetService->createMeetEvent(
                        $session->title,
                        $start,
                        (int) $session->duration_minutes,
                        $session->timezone,
                        (string) $session->getKey(),
                    )
                    : $this->googleMeetService->updateMeetEvent(
                        $session->meet_event_id,
                        $session->title,
                        $start,
                        (int) $session->duration_minutes,
                        $session->timezone,
                    );

                if ($meetData === null) {
                    // A provider-confirmed 404 means the old event is already
                    // absent; recreating cannot leave two active events.
                    $meetData = $this->googleMeetService->createMeetEvent(
                        $session->title,
                        $start,
                        (int) $session->duration_minutes,
                        $session->timezone,
                        (string) $session->getKey(),
                    );
                }

                if (empty($meetData['event_id'])) {
                    throw new \UnexpectedValueException('Google Meet returned no event identifier.');
                }

                $session->forceFill([
                    'meet_event_id' => $meetData['event_id'],
                    'meet_link' => $meetData['meet_link'] ?? $session->meet_link,
                    'meet_synced_schedule_hash' => MeetAutomationRules::scheduleFingerprint($session),
                    'meet_attempts' => 0,
                    'meet_last_attempt_at' => DbTime::toRaw($now),
                    'meet_last_error' => null,
                ])->save();
            } catch (Throwable) {
                $this->recordFailure($session, 'update', $now);
            }
        });
    }

    public function deleteCanceled(string $sessionId, DateTimeInterface $now): void
    {
        DB::transaction(function () use ($sessionId, $now): void {
            $session = Session::query()->whereKey($sessionId)->lockForUpdate()->first();
            if (! $session || $session->status !== 'canceled' || ! $session->meet_event_id) {
                return;
            }

            try {
                $this->googleMeetService->deleteEvent($session->meet_event_id);
                $session->forceFill([
                    'meet_attempts' => 0,
                    'meet_last_attempt_at' => DbTime::toRaw($now),
                    'meet_last_error' => null,
                ])->save();
            } catch (Throwable) {
                $this->recordFailure($session, 'delete', $now);
            }
        });
    }

    private function recordFailure(Session $session, string $operation, DateTimeInterface $now): void
    {
        $attempts = ((int) $session->meet_attempts) + 1;
        $operationLabel = $operation === 'delete' ? 'deletion' : $operation;
        $session->forceFill([
            'meet_attempts' => $attempts,
            'meet_last_attempt_at' => DbTime::toRaw($now),
            'meet_last_error' => "Google Meet {$operationLabel} failed.",
        ])->save();

        Log::warning("scheduling.meet_{$operation}_failed", [
            'session_id' => (string) $session->getKey(),
            'attempts' => $attempts,
        ]);
    }

    public function ensure(Session $session, DateTimeInterface $now): ?array
    {
        return DB::transaction(function () use ($session, $now): ?array {
            $lockedSession = Session::query()->whereKey($session->getKey())->lockForUpdate()->first();

            if (! $lockedSession) {
                return null;
            }

            $scheduledAt = $lockedSession->getAttributes()['scheduled_at'] ?? null;
            if (
                $lockedSession->status !== 'scheduled'
                || $scheduledAt === null
                || ! DbTime::fromRaw($scheduledAt)->greaterThan(CarbonImmutable::instance($now)->utc())
            ) {
                return null;
            }

            if ($lockedSession->meet_link || $lockedSession->meet_event_id) {
                return [
                    'meet_link' => $lockedSession->meet_link,
                    'event_id' => $lockedSession->meet_event_id,
                ];
            }

            if (! MeetAutomationRules::eligible($lockedSession, $now)) {
                return null;
            }

            try {
                $meetData = $this->googleMeetService->createMeetEvent(
                    $lockedSession->title,
                    DbTime::fromRaw($lockedSession->getAttributes()['scheduled_at']),
                    (int) $lockedSession->duration_minutes,
                    $lockedSession->timezone,
                    (string) $lockedSession->getKey(),
                );

                if (empty($meetData['meet_link']) || empty($meetData['event_id'])) {
                    throw new \UnexpectedValueException('Google Meet did not return an event and link.');
                }
            } catch (Throwable) {
                $attempts = ((int) $lockedSession->meet_attempts) + 1;
                $lockedSession->forceFill([
                    'meet_attempts' => $attempts,
                    'meet_last_attempt_at' => DbTime::toRaw($now),
                    'meet_last_error' => 'Google Meet creation failed.',
                ])->save();

                Log::warning('scheduling.meet_failed', [
                    'session_id' => (string) $lockedSession->getKey(),
                    'attempts' => $attempts,
                ]);

                return null;
            }

            $lockedSession->forceFill([
                'meet_link' => $meetData['meet_link'],
                'meet_event_id' => $meetData['event_id'],
                'meet_synced_schedule_hash' => MeetAutomationRules::scheduleFingerprint($lockedSession),
                'meet_attempts' => 0,
                'meet_last_attempt_at' => DbTime::toRaw($now),
                'meet_last_error' => null,
            ])->save();

            return [
                'meet_link' => $lockedSession->meet_link,
                'event_id' => $lockedSession->meet_event_id,
            ];
        });
    }
}
