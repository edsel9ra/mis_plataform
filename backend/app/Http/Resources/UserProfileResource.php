<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
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
            'role' => $this->role,
            'client_type' => $this->client_type,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'provider' => $this->provider,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'personality_assessment' => PersonalityAssessmentResource::make($this->whenLoaded('personalityAssessment')),
            'skills' => UserSkillResource::collection($this->whenLoaded('skills')),
            'company' => CompanyResource::make($this->whenLoaded('company')),
            'employee' => EmployeeResource::make($this->whenLoaded('employee')),
        ];
    }
}
