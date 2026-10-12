<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecommendationMentorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'avatar' => $this->avatar,
            'client_type' => $this->client_type,
            'compatibility_score' => $this->compatibility_score,
            'active_mentees_count' => $this->active_mentees_count,
            'skills' => UserSkillResource::collection($this->whenLoaded('skills')),
        ];
    }
}
