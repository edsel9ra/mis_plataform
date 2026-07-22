<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MentorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'avatar' => $this->avatar,
            'bio' => $this->bio,
            'client_type' => $this->client_type,
            'created_at' => $this->created_at,
            'personality_assessment' => PersonalityAssessmentResource::make($this->whenLoaded('personalityAssessment')),
            'skills' => UserSkillResource::collection($this->whenLoaded('skills')),
            'active_mentees_count' => $this->whenCounted('active_mentees_count'),
            'relationships_count' => $this->whenCounted('mentorRelationships'),
        ];
    }
}
