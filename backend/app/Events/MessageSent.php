<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Message $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("relationship.{$this->message->relationship_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'relationship_id' => $this->message->relationship_id,
            'sender_id' => $this->message->sender_id,
            'content' => $this->message->content,
            'type' => $this->message->type,
            'created_at' => $this->message->created_at,
        ];
    }
}
