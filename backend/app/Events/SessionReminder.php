<?php

namespace App\Events;

use App\Models\Session;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class SessionReminder implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Session $session
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("relationship.{$this->session->relationship_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.reminder';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'title' => $this->session->title,
            'scheduled_at' => $this->session->scheduled_at,
            'meet_link' => $this->session->meet_link,
        ];
    }
}
