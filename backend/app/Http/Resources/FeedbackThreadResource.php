<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedbackThreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'relationship_id' => $this->relationship_id,
            'challenge_id' => $this->challenge_id,
            'mentor_id' => $this->mentor_id,
            'mentee_id' => $this->mentee_id,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'entries' => FeedbackEntryResource::collection($this->whenLoaded('entries')),
        ];
    }
}
