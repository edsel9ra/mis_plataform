<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** A persisted session state transition, broadcast only after its transaction commits. */
class SessionLifecycleTransitioned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $sessionId,
        public string $relationshipId,
        public string $previousStatus,
        public string $newStatus,
        public string $occurredAt,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("relationship.{$this->relationshipId}")];
    }

    public function broadcastAs(): string
    {
        return 'session.lifecycle.transitioned';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'previous_status' => $this->previousStatus,
            'status' => $this->newStatus,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
