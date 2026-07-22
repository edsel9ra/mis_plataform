<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'relationship_id' => $this->relationship_id,
            'sender_id' => $this->sender_id,
            'content' => $this->content,
            'type' => $this->type,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'sender' => UserResource::make($this->whenLoaded('sender')),
        ];
    }
}
