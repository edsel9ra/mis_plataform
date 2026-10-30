<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

class SessionReminder implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Notification $notification
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->notification->user_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.reminder';
    }

    public function broadcastWith(): array
    {
        return array_merge(
            ['notification_id' => $this->notification->id],
            $this->notification->data ?? [],
        );
    }

    public function middleware(): array
    {
        return [new \App\Services\Scheduling\ConfirmSessionReminderDelivery((string) $this->notification->getKey())];
    }

    public function failed(?Throwable $exception = null): void
    {
        $notification = $this->notification;
        $updated = Notification::query()
            ->whereKey($notification->getKey())
            ->where('status', 'pending')
            ->update(['status' => 'failed', 'updated_at' => now()]);

        if ($updated === 1) {
            \Illuminate\Support\Facades\Log::warning('scheduling.broadcast_delivery_failed', [
                'notification_id' => (string) $notification->getKey(),
                'session_id' => $notification->data['session_id'] ?? null,
                'exception' => $exception ? $exception::class : null,
            ]);
        }
    }
}
