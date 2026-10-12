<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CohortResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_by' => $this->created_by,
            'name' => $this->name,
            'description' => $this->description,
            'max_members' => $this->max_members,
            'plan_id' => $this->plan_id,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'creator' => UserSummaryResource::make($this->whenLoaded('creator')),
            'members' => CohortMemberResource::collection($this->whenLoaded('members')),
            'plan' => PlanResource::make($this->whenLoaded('plan')),
        ];
    }
}
