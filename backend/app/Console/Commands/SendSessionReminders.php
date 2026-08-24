<?php

namespace App\Console\Commands;

use App\Events\SessionReminder;
use App\Models\Notification;
use App\Models\Session;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SendSessionReminders extends Command
{
    protected $signature = 'app:send-session-reminders';
    protected $description = 'Send reminders for upcoming sessions';

    public function handle(): int
    {
        $now = now();

        foreach ([24, 2, 1] as $hoursUntil) {
            $target = $now->copy()->addHours($hoursUntil);
            $upcomingSessions = Session::where('status', 'scheduled')
                ->whereBetween('scheduled_at', [$target->copy()->subMinute(), $target->copy()->addMinute()])
                ->with(['relationship.mentor', 'attendees.user'])
                ->get();

            foreach ($upcomingSessions as $session) {
                $users = $session->attendees->pluck('user')->filter();

                foreach ($users as $user) {
                    $created = Notification::query()->insertOrIgnore([
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'type' => 'session_reminder',
                        'dedupe_key' => "session_reminder:{$session->id}:{$user->id}:{$hoursUntil}",
                        'data' => json_encode([
                            'session_id' => $session->id,
                            'title' => $session->title,
                            'scheduled_at' => $session->scheduled_at->toIso8601String(),
                            'meet_link' => $session->meet_link,
                            'hours_until' => $hoursUntil,
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if ($created === 1) {
                        broadcast(new SessionReminder($session));
                    }
                }

                $this->info("Reminder sent for session {$session->id} ({$hoursUntil}h before)");
            }
        }

        return self::SUCCESS;
    }
}
