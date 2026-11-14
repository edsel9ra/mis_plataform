<?php

namespace App\Services\Scheduling;

use App\Events\NotificationPushed;
use App\Events\SessionCanceled;
use App\Events\SessionReminder;
use App\Events\SessionRescheduled;
use App\Models\MentorshipRelationship;
use App\Models\Notification;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\User;
use App\Services\Scheduling\Rules\ParticipantRules;
use App\Services\Scheduling\Rules\ReminderRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SessionNotificationService
{
    public function handleCreated(string $sessionId, CarbonImmutable $now): void
    {
        $session = $this->sessionForEvent($sessionId);
        if ($session === null || $session->status !== 'scheduled') {
            return;
        }

        $scheduledAt = CarbonImmutable::instance($session->scheduled_at)->utc();
        $now = $now->utc();
        if (! ReminderRules::isImminent($scheduledAt, $now)) {
            return;
        }

        foreach ($this->recipients($session, $now, false) as $recipient) {
            $this->sendImmediateReminder(
                $session,
                $recipient,
                $scheduledAt,
                $session->timezone,
                $this->locale($recipient),
                $now,
            );
        }
    }

    public function handleRescheduled(SessionRescheduled $event): void
    {
        $session = $this->sessionForEvent($event->sessionId);
        if ($session === null || $session->status !== 'scheduled') {
            return;
        }

        $scheduledAt = CarbonImmutable::parse($event->newSchedule['scheduled_at'])->utc();
        if (! $session->scheduled_at->equalTo($scheduledAt)) {
            return;
        }

        $now = CarbonImmutable::parse($event->occurredAt)->utc();
        $participants = $this->recipients($session, $now, false);
        $participantIds = array_map(fn (User $user): string => (string) $user->id, $participants);
        $recipients = $this->recipients($session, $now);

        foreach ($recipients as $recipient) {
            $locale = $this->locale($recipient);
            $timezone = (string) ($event->newSchedule['timezone'] ?? $session->timezone);
            $previousSchedule = $event->previousSchedule;
            $newSchedule = $event->newSchedule;
            $data = [
                'event_type' => SessionRescheduled::TYPE,
                'event_id' => $event->eventId,
                'session_id' => $session->id,
                'title' => $session->title,
                'previous_schedule' => $previousSchedule,
                'new_schedule' => $newSchedule,
                'scheduled_at' => $scheduledAt->toIso8601String(),
                'timezone' => $timezone,
                'message' => __('scheduling.change_message', [
                    'title' => $session->title,
                    'previous_at' => $this->localizedDateTime(
                        CarbonImmutable::parse($previousSchedule['scheduled_at'])->utc(),
                        (string) ($previousSchedule['timezone'] ?? $timezone),
                        $locale,
                    ),
                    'new_at' => $this->localizedDateTime($scheduledAt, $timezone, $locale),
                    'timezone' => $timezone,
                    'reason' => $newSchedule['reason'] ?? '',
                ], $locale),
            ];

            $this->deliver(
                $session,
                $recipient,
                ReminderRules::changeKey($session->id, $event->eventId, $recipient->id),
                'session_change',
                $data,
                $now,
            );

            if (in_array((string) $recipient->id, $participantIds, true)
                && ReminderRules::isImminent($scheduledAt, $now)) {
                $this->sendImmediateReminder($session, $recipient, $scheduledAt, $timezone, $locale, $now);
            }
        }
    }

    public function handleCanceled(SessionCanceled $event): void
    {
        $session = $this->sessionForEvent($event->sessionId);
        if ($session === null || $session->status !== 'canceled') {
            return;
        }

        $now = CarbonImmutable::parse($event->occurredAt)->utc();
        foreach ($this->recipients($session, $now) as $recipient) {
            $locale = $this->locale($recipient);
            $data = [
                'event_type' => SessionCanceled::TYPE,
                'event_id' => $event->eventId,
                'session_id' => $session->id,
                'title' => $session->title,
                'occurred_at' => $event->occurredAt,
                'reason' => $event->reason,
                'late' => $event->late,
                'lead_minutes' => $event->leadMinutes,
                'message' => __('scheduling.cancellation_message', [
                    'title' => $session->title,
                    'reason' => $event->reason,
                    'late_notice' => $event->late
                        ? __('scheduling.cancellation_late', [], $locale)
                        : '',
                ], $locale),
            ];

            $this->deliver(
                $session,
                $recipient,
                ReminderRules::changeKey($session->id, $event->eventId, $recipient->id),
                'session_change',
                $data,
                $now,
            );
        }
    }

    private function sessionForEvent(string $sessionId): ?Session
    {
        return Session::query()
            ->with([
                'relationship.source',
                'attendees',
                'participantExclusions',
            ])
            ->find($sessionId);
    }

    /**
     * @return list<User>
     */
    private function recipients(Session $session, CarbonImmutable $now, bool $includeResponsible = true): array
    {
        $relationship = $session->relationship;
        if ($relationship === null) {
            return [];
        }

        $attendees = $session->attendees
            ->map(fn (SessionAttendee $attendee): array => [
                'user_id' => (string) $attendee->user_id,
                'created_at' => CarbonImmutable::instance($attendee->created_at),
                'left_at' => $attendee->left_at === null
                    ? null
                    : CarbonImmutable::instance($attendee->left_at),
            ])
            ->all();
        $participantIds = ParticipantRules::participantsAtEnd(
            [
                'mentor_id' => (string) $relationship->mentor_id,
                'excluded_user_ids' => $session->participantExclusions
                    ->pluck('user_id')
                    ->map(fn (string $userId): string => $userId)
                    ->all(),
            ],
            $attendees,
            CarbonImmutable::instance($session->end_at),
            $now,
        );

        if (! $includeResponsible) {
            return User::query()->whereIn('id', array_values(array_unique($participantIds)))->get()->all();
        }

        $responsibleId = match ($relationship->source_type) {
            'family_group' => $relationship->source?->head_user_id,
            'cohort' => $relationship->source?->created_by,
            'company' => $relationship->source?->admin_id,
            default => null,
        };

        if ($responsibleId !== null) {
            $responsible = User::query()->find($responsibleId);
            if ($responsible !== null && MentorshipRelationship::userCanManageSource(
                $responsible,
                $relationship->source_type,
                (string) $relationship->source_id,
            )) {
                $participantIds[] = (string) $responsible->id;
            }
        }

        return User::query()
            ->whereIn('id', array_values(array_unique($participantIds)))
            ->get()
            ->all();
    }

    private function sendImmediateReminder(
        Session $session,
        User $recipient,
        CarbonImmutable $scheduledAt,
        string $timezone,
        string $locale,
        CarbonImmutable $now,
    ): void {
        $reminderKeys = array_map(
            fn (int|string $horizon): string => ReminderRules::reminderKey(
                (string) $session->id,
                (string) $recipient->id,
                $horizon,
                $scheduledAt,
            ),
            [...config('scheduling.horizons_hours', [24, 2, 1]), 'immediate'],
        );

        if (Notification::query()
            ->where('type', 'session_reminder')
            ->where('user_id', $recipient->id)
            ->where('status', 'sent')
            ->whereIn('dedupe_key', $reminderKeys)
            ->exists()) {
            return;
        }

        $meetLink = $session->meet_link;
        $meetLinkText = $meetLink === null
            ? __('scheduling.reminder_meet_pending', [], $locale)
            : __('scheduling.reminder_meet_link', ['meet_link' => $meetLink], $locale);
        $localizedDate = $this->localizedDateTime($scheduledAt, $timezone, $locale);
        $data = [
            'event_type' => 'session.reminder.immediate',
            'session_id' => $session->id,
            'title' => $session->title,
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'timezone' => $timezone,
            'hours_until' => 'immediate',
            'meet_link' => $meetLink,
            'message' => __('scheduling.reminder_immediate_message', [
                'title' => $session->title,
                'scheduled_at' => $localizedDate,
                'timezone' => $timezone,
                'meet_link_text' => $meetLinkText,
            ], $locale),
        ];

        $this->deliver(
            $session,
            $recipient,
            ReminderRules::reminderKey($session->id, $recipient->id, 'immediate', $scheduledAt),
            'session_reminder',
            $data,
            $now,
        );
    }

    private function deliver(
        Session $session,
        User $recipient,
        string $dedupeKey,
        string $type,
        array $data,
        CarbonImmutable $now,
    ): void {
        $inserted = Notification::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'user_id' => $recipient->id,
            'type' => $type,
            'dedupe_key' => $dedupeKey,
            'status' => 'pending',
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $notification = Notification::query()->where('dedupe_key', $dedupeKey)->first();

        if ($notification === null) {
            return;
        }

        if ($inserted !== 1) {
            if (! ReminderRules::shouldAttempt($notification->status)) {
                return;
            }

            $claimed = Notification::query()
                ->whereKey($notification->id)
                ->where('status', 'failed')
                ->update([
                    'status' => 'pending',
                    'sent_at' => null,
                    'data' => json_encode($data, JSON_THROW_ON_ERROR),
                    'updated_at' => $now,
                ]);

            if ($claimed !== 1) {
                return;
            }

            $notification->refresh();
        }

        try {
            broadcast(new SessionReminder($notification));
        } catch (Throwable $exception) {
            Notification::query()->whereKey($notification->id)->where('status', 'pending')->update([
                'status' => 'failed',
                'updated_at' => $now,
            ]);
            Log::warning('scheduling.change_notification_delivery_failed', [
                'session_id' => $session->id,
                'notification_id' => $notification->id,
                'exception' => $exception::class,
            ]);
        }

        if ($inserted === 1) {
            NotificationPushed::dispatch($notification);
        }
    }

    private function locale(User $user): string
    {
        return in_array($user->locale, config('app.available_locales', []), true)
            ? $user->locale
            : config('app.fallback_locale', 'es');
    }

    private function localizedDateTime(CarbonImmutable $dateTime, string $timezone, string $locale): string
    {
        return $dateTime
            ->setTimezone($timezone)
            ->locale($locale)
            ->translatedFormat('l, j F Y H:i');
    }
}
