<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminMentorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'sex' => $this->sex,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'avatar' => $this->avatar,
            'client_type' => $this->client_type,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            'personality_assessment' => PersonalityAssessmentResource::make($this->whenLoaded('personalityAssessment')),
            'skills' => UserSkillResource::collection($this->whenLoaded('skills')),
            'active_mentees_count' => $this->when(isset($this->active_mentees_count), (int) $this->active_mentees_count),
            'mentor_relationships' => $this->whenLoaded('mentorRelationships'),
        ];
    }
}
