<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NeurocognitiveObservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dimensions = is_array($this->dimensions) ? $this->dimensions : [];

        return [
            'id' => $this->id,
            'relationship_id' => $this->relationship_id,
            'challenge_id' => $this->challenge_id,
            'mentor_id' => $this->mentor_id,
            'mentee_id' => $this->mentee_id,
            'source' => 'mentor_observation',
            'attention_score' => $this->attention_score,
            'engagement_score' => $this->engagement_score,
            'dimensions' => $this->dimensions,
            'focus' => $dimensions['focus'] ?? $this->attention_score,
            'attention' => $dimensions['focus'] ?? $this->attention_score,
            'regulation' => $dimensions['regulation'] ?? null,
            'emotional_regulation' => $dimensions['regulation'] ?? null,
            'stress' => $dimensions['stress'] ?? null,
            'energy' => $dimensions['energy'] ?? null,
            'note' => $this->note,
            'observed_at' => $this->observed_at,
            'created_at' => $this->created_at,
        ];
    }
}
