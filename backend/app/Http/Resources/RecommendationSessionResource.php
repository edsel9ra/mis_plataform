<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecommendationSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_type' => $this->session_type,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'duration_minutes' => $this->duration_minutes,
            'relationship' => $this->whenLoaded('relationship', fn () => [
                'id' => $this->relationship->id,
                'mentor' => UserSummaryResource::make($this->relationship->mentor),
            ]),
        ];
    }
}
