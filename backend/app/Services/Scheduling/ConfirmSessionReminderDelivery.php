<?php

namespace App\Services\Scheduling;

use App\Models\Notification;
use Closure;

/**
 * Runs inside Laravel's queued BroadcastEvent job. A successful broadcaster
 * return is the delivery confirmation; enqueueing the job is not.
 */
final class ConfirmSessionReminderDelivery
{
    public function __construct(private readonly string $notificationId) {}

    public function handle(mixed $job, Closure $next): mixed
    {
        $result = $next($job);

        Notification::query()
            ->whereKey($this->notificationId)
            ->where('status', 'pending')
            ->update([
                'status' => 'sent',
                'sent_at' => now(),
                'updated_at' => now(),
            ]);

        return $result;
    }
}
