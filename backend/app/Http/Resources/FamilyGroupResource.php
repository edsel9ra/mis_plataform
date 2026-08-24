<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FamilyGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'head_user_id' => $this->head_user_id,
            'family_name' => $this->family_name,
            'description' => $this->description,
            'plan_id' => $this->plan_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'head' => UserSummaryResource::make($this->whenLoaded('head')),
            'members' => FamilyMemberResource::collection($this->whenLoaded('members')),
            'plan' => PlanResource::make($this->whenLoaded('plan')),
        ];
    }
}
