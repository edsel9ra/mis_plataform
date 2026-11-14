<?php

namespace App\Console\Commands;

use App\Events\NotificationPushed;
use App\Events\SessionReminder;
use App\Models\Notification;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\User;
use App\Services\Scheduling\Rules\ParticipantRules;
use App\Services\Scheduling\Rules\ReminderRules;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SendSessionReminders extends Command
{
    protected $signature = 'app:send-session-reminders';
    protected $description = 'Send reminders for upcoming sessions';

    public function handle(): int
    {
        $now = CarbonImmutable::instance(now());
        $failedNotificationIds = Notification::query()
            ->where('type', 'session_reminder')
            ->where('status', 'failed')
            ->pluck('id')
            ->all();
        $failedChangeNotificationIds = Notification::query()
            ->where('type', 'session_change')
            ->where('status', 'failed')
            ->pluck('id')
            ->all();
        $processed = 0;

        foreach (config('scheduling.horizons_hours', [24, 2, 1]) as $hoursUntil) {
            $target = $now->copy()->addHours($hoursUntil);
            $upcomingSessions = Session::where('status', 'scheduled')
                ->whereHas('relationship')
                ->whereBetween('scheduled_at', [$target->copy()->subMinute(), $target->copy()->addMinute()])
                ->with([
                    'relationship:id,source_type,source_id,mentor_id',
                    'attendees:id,session_id,user_id,created_at,left_at',
                    'participantExclusions:id,session_id,user_id',
                ])
                ->get();

            foreach ($upcomingSessions as $session) {
                if ($hoursUntil === 24 && ReminderRules::isImminent($session->scheduled_at, $now)) {
                    // The immediate notice substitutes for the 24-hour horizon;
                    // later 2h/1h horizons remain independently scheduled.
                    continue;
                }

                if (! in_array($hoursUntil, ReminderRules::dueHorizons($session->scheduled_at, $now), true)) {
                    continue;
                }

                foreach ($this->reminderRecipients($session, $now) as $user) {
                    $this->sendReminder($session, $user, (int) $hoursUntil, $now);
                    $processed++;
                }
            }
        }

        $this->retryFailedReminders($now, $failedNotificationIds);
        $this->retryFailedChangeNotifications($now, $failedChangeNotificationIds);

        $this->comment("Processed {$processed} eligible session reminder recipient(s).");

        return self::SUCCESS;
    }

    /**
     * @return list<User>
     */
    private function reminderRecipients(Session $session, CarbonImmutable $now): array
    {
        if ($session->relationship === null) {
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
                'mentor_id' => (string) $session->relationship->mentor_id,
                'excluded_user_ids' => $session->participantExclusions
                    ->pluck('user_id')
                    ->map(fn (string $userId): string => $userId)
                    ->all(),
            ],
            $attendees,
            CarbonImmutable::instance($session->end_at),
            $now,
        );

        return User::query()
            ->whereIn('id', $participantIds)
            ->get()
            ->all();
    }

    private function sendReminder(Session $session, User $user, int $hoursUntil, CarbonImmutable $now): void
    {
        $dedupeKey = ReminderRules::reminderKey(
            (string) $session->id,
            (string) $user->id,
            $hoursUntil,
            $session->scheduled_at,
        );
        $data = $this->reminderData($session, $user, $hoursUntil);

        $inserted = Notification::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'session_reminder',
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
            Log::warning('scheduling.reminder_delivery_failed', [
                'session_id' => $session->id,
                'notification_id' => $notification->id,
                'horizon_hours' => $hoursUntil,
                'exception' => $exception::class,
            ]);
        }

        if ($inserted === 1) {
            NotificationPushed::dispatch($notification);
        }
    }

    private function retryFailedReminders(CarbonImmutable $now, array $failedNotificationIds): void
    {
        $failedNotifications = Notification::query()
            ->where('type', 'session_reminder')
            ->where('status', 'failed')
            ->whereIn('id', $failedNotificationIds)
            ->orderBy('created_at')
            ->get();

        foreach ($failedNotifications as $notification) {
            $data = $notification->data ?? [];
            $sessionId = $data['session_id'] ?? null;
            $horizon = $data['hours_until'] ?? null;
            if ($sessionId === null || ! in_array($horizon, [...config('scheduling.horizons_hours', [24, 2, 1]), 'immediate'], true)) {
                continue;
            }

            $session = Session::query()
                ->whereKey($sessionId)
                ->where('status', 'scheduled')
                ->where('scheduled_at', '>', $now)
                ->with([
                    'relationship:id,source_type,source_id,mentor_id',
                    'attendees:id,session_id,user_id,created_at,left_at',
                    'participantExclusions:id,session_id,user_id',
                ])
                ->first();
            if ($session === null) {
                continue;
            }

            if ($horizon === 24 && ReminderRules::isImminent($session->scheduled_at, $now)) {
                continue;
            }

            $expectedKey = ReminderRules::reminderKey(
                (string) $session->id,
                (string) $notification->user_id,
                $horizon,
                $session->scheduled_at,
            );
            $recipient = collect($this->reminderRecipients($session, $now))
                ->first(fn (User $user): bool => (string) $user->id === (string) $notification->user_id);

            if ($notification->dedupe_key !== $expectedKey || $recipient === null) {
                continue;
            }

            $freshData = $this->reminderData($session, $recipient, $horizon);
            $claimed = Notification::query()
                ->whereKey($notification->id)
                ->where('status', 'failed')
                ->update([
                    'status' => 'pending',
                    'data' => json_encode($freshData, JSON_THROW_ON_ERROR),
                    'updated_at' => $now,
                ]);
            if ($claimed !== 1) {
                continue;
            }

            try {
                broadcast(new SessionReminder($notification->fresh()));
            } catch (Throwable $exception) {
                Notification::query()->whereKey($notification->id)->where('status', 'pending')->update([
                    'status' => 'failed',
                    'updated_at' => $now,
                ]);
                Log::warning('scheduling.reminder_delivery_failed', [
                    'session_id' => $session->id,
                    'notification_id' => $notification->id,
                    'horizon_hours' => $horizon,
                    'exception' => $exception::class,
                ]);
            }
        }
    }

    private function reminderData(Session $session, User $user, int|string $horizon): array
    {
        $locale = in_array($user->locale, config('app.available_locales', []), true)
            ? $user->locale
            : config('app.fallback_locale', 'es');
        $scheduledAt = CarbonImmutable::instance($session->scheduled_at)->utc();
        $localizedScheduledAt = $scheduledAt
            ->setTimezone($session->timezone)
            ->locale($locale)
            ->translatedFormat('l, j F Y H:i');
        $meetLinkText = $session->meet_link === null
            ? __('scheduling.reminder_meet_pending', [], $locale)
            : __('scheduling.reminder_meet_link', ['meet_link' => $session->meet_link], $locale);

        if ($horizon === 'immediate') {
            return [
                'event_type' => 'session.reminder.immediate',
                'session_id' => $session->id,
                'title' => $session->title,
                'scheduled_at' => $scheduledAt->toIso8601String(),
                'timezone' => $session->timezone,
                'hours_until' => 'immediate',
                'meet_link' => $session->meet_link,
                'message' => __('scheduling.reminder_immediate_message', [
                    'title' => $session->title,
                    'scheduled_at' => $localizedScheduledAt,
                    'timezone' => $session->timezone,
                    'meet_link_text' => $meetLinkText,
                ], $locale),
            ];
        }

        return [
            'session_id' => $session->id,
            'title' => $session->title,
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'timezone' => $session->timezone,
            'hours_until' => (int) $horizon,
            'meet_link' => $session->meet_link,
            'message' => __('scheduling.reminder_message', [
                'title' => $session->title,
                'scheduled_at' => $localizedScheduledAt,
                'timezone' => $session->timezone,
                'hours' => (int) $horizon,
                'meet_link_text' => $meetLinkText,
            ], $locale),
        ];
    }

    /**
     * Change notices retain their event snapshot and recipient in the durable
     * notification row, so a failed queued broadcast can be retried without
     * requiring the original post-commit event to be replayed.
     *
     * @param list<string> $failedNotificationIds
     */
    private function retryFailedChangeNotifications(CarbonImmutable $now, array $failedNotificationIds): void
    {
        $notifications = Notification::query()
            ->whereIn('id', $failedNotificationIds)
            ->where('type', 'session_change')
            ->where('status', 'failed')
            ->orderBy('created_at')
            ->get();

        foreach ($notifications as $notification) {
            $claimed = Notification::query()
                ->whereKey($notification->id)
                ->where('status', 'failed')
                ->update(['status' => 'pending', 'updated_at' => $now]);
            if ($claimed !== 1) {
                continue;
            }

            try {
                broadcast(new SessionReminder($notification->fresh()));
            } catch (Throwable $exception) {
                Notification::query()->whereKey($notification->id)->where('status', 'pending')->update([
                    'status' => 'failed',
                    'updated_at' => $now,
                ]);
                Log::warning('scheduling.change_notification_delivery_failed', [
                    'session_id' => $notification->data['session_id'] ?? null,
                    'notification_id' => $notification->id,
                    'exception' => $exception::class,
                ]);
            }
        }
    }
}
