<?php

namespace App\Console\Commands;

use App\Events\SessionReminder;
use App\Models\Notification;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendSessionReminders extends Command
{
    protected $signature = 'app:send-session-reminders';
    protected $description = 'Send reminders for upcoming sessions';

    public function handle(): void
    {
        $upcomingSessions = Session::where('status', 'scheduled')
            ->whereBetween('scheduled_at', [now(), now()->addHours(24)])
            ->with(['relationship.mentor', 'attendees.user'])
            ->get();

        foreach ($upcomingSessions as $session) {
            $hoursUntil = Carbon::now()->diffInHours($session->scheduled_at);

            if (in_array((int) $hoursUntil, [24, 2, 1])) {
                $users = $session->attendees->pluck('user');

                foreach ($users as $user) {
                    Notification::create([
                        'user_id' => $user->id,
                        'type' => 'session_reminder',
                        'data' => [
                            'session_id' => $session->id,
                            'title' => $session->title,
                            'scheduled_at' => $session->scheduled_at->toIso8601String(),
                            'meet_link' => $session->meet_link,
                            'hours_until' => (int) $hoursUntil,
                        ],
                    ]);

                    broadcast(new SessionReminder($session));
                }

                $this->info("Reminder sent for session {$session->id} ({$hoursUntil}h before)");
            }
        }
    }
}
