<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Enums\SessionAttendanceEventType;
use App\Enums\SessionStatus;
use App\Events\SessionLifecycleTransitioned;
use App\Models\Session;
use App\Models\SessionAttendanceEvent;
use App\Models\SessionAttendee;
use App\Services\Scheduling\Rules\LifecycleRules;
use App\Services\Scheduling\Rules\ParticipantRules;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvanceSessionLifecycle extends Command
{
    protected $signature = 'app:advance-session-lifecycle';

    protected $description = 'Advance due mentorship session lifecycle states.';

    public function handle(): int
    {
        // Persisted session timestamps are UTC instants stored in timestamp columns.
        $now = CarbonImmutable::now('UTC');
        $autoCloseHours = (int) config('scheduling.not_completed_after_hours', 48);
        $autoCloseCutoff = $now->subHours($autoCloseHours);

        $sessionIds = Session::query()
            ->whereIn('status', [SessionStatus::Scheduled->value, SessionStatus::InProgress->value])
            ->where(function ($query) use ($now, $autoCloseCutoff): void {
                $query->where(function ($dueToStart) use ($now): void {
                    $dueToStart->where('status', SessionStatus::Scheduled->value)
                        ->where('scheduled_at', '<=', $now);
                })->orWhere('end_at', '<=', $autoCloseCutoff);
            })
            ->pluck('id');

        foreach ($sessionIds as $sessionId) {
            $transition = DB::transaction(function () use ($sessionId, $now): ?array {
                $session = Session::query()->whereKey($sessionId)->lockForUpdate()->first();

                if (! $session || ! in_array($session->status, [
                    SessionStatus::Scheduled->value,
                    SessionStatus::InProgress->value,
                ], true)) {
                    return null;
                }

                $attributes = $session->getAttributes();
                if ($attributes['scheduled_at'] === null || $attributes['end_at'] === null) {
                    return null;
                }

                $target = LifecycleRules::next(
                    SessionStatus::from($session->status),
                    CarbonImmutable::parse($attributes['scheduled_at'], 'UTC'),
                    CarbonImmutable::parse($attributes['end_at'], 'UTC'),
                    $now,
                );

                if ($target === null || $target->value === $session->status) {
                    return null;
                }

                $previousStatus = $session->status;
                if ($target === SessionStatus::NotCompleted) {
                    $this->ensureAttendanceInitializations($session, $now);
                }

                $session->forceFill(['status' => $target->value])->save();
                $occurredAt = $now->toIso8601String();

                activity('scheduling')
                    ->performedOn($session)
                    ->event('system.session.status_transition')
                    ->withProperties([
                        'session_id' => $session->id,
                        'actor' => 'system',
                        'changed_fields' => ['status'],
                        'before' => ['status' => $previousStatus],
                        'after' => ['status' => $target->value],
                        'occurred_at' => $occurredAt,
                    ])
                    ->log('system.session.status_transition');

                return [
                    'session_id' => (string) $session->id,
                    'relationship_id' => (string) $session->relationship_id,
                    'previous_status' => $previousStatus,
                    'new_status' => $target->value,
                    'occurred_at' => $occurredAt,
                ];
            });

            if ($transition === null) {
                continue;
            }

            Log::info('scheduling.lifecycle_transitioned', $transition);
            event(new SessionLifecycleTransitioned(
                $transition['session_id'],
                $transition['relationship_id'],
                $transition['previous_status'],
                $transition['new_status'],
                $transition['occurred_at'],
            ));
        }

        return self::SUCCESS;
    }

    private function ensureAttendanceInitializations(Session $session, DateTimeImmutable $now): void
    {
        $attributes = $session->getAttributes();
        $endAt = CarbonImmutable::parse($attributes['end_at'], 'UTC');
        $attendees = $session->attendees()->get();
        $exclusions = $session->participantExclusions()->pluck('user_id')->map(
            static fn ($id): string => (string) $id,
        )->all();
        $participantIds = ParticipantRules::participantsAtEnd(
            [
                'mentor_id' => (string) $session->relationship()->value('mentor_id'),
                'excluded_user_ids' => $exclusions,
            ],
            $attendees->map(static function (SessionAttendee $attendee): array {
                $raw = $attendee->getAttributes();

                return [
                    'user_id' => (string) $attendee->user_id,
                    'created_at' => CarbonImmutable::parse($raw['created_at'], 'UTC'),
                    'left_at' => $raw['left_at'] === null
                        ? null
                        : CarbonImmutable::parse($raw['left_at'], 'UTC'),
                ];
            })->all(),
            $endAt,
            $now,
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
                'occurred_at' => $endAt,
            ]);
        }
    }
}
