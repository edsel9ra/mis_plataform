<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChallengeCheckInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'relationship_id' => $this->relationship_id,
            'challenge_id' => $this->challenge_id,
            'mentee_id' => $this->mentee_id,
            'source' => $this->source,
            'status' => $this->status,
            'attention_score' => $this->attention_score,
            'focus' => $this->attention_score,
            'attention' => $this->attention_score,
            'note' => $this->note,
            'checked_in_at' => $this->checked_in_at,
            'created_at' => $this->created_at,
            'author' => UserSummaryResource::make($this->whenLoaded('author')),
        ];
    }
}
