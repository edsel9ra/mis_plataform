<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmotionalCheckInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'relationship_id' => $this->relationship_id,
            'mentee_id' => $this->mentee_id,
            'mood_score' => $this->mood_score,
            'stress_score' => $this->stress_score,
            'status' => $this->status ?? 'completed',
            'note' => $this->note,
            'checked_in_at' => $this->checked_in_at,
            'created_at' => $this->created_at,
        ];
    }
}
