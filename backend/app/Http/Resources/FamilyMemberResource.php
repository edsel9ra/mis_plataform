<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FamilyMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_group_id' => $this->family_group_id,
            'user_id' => $this->user_id,
            'full_name' => $this->full_name,
            'age' => $this->age,
            'relationship' => $this->relationship,
            'user' => UserSummaryResource::make($this->whenLoaded('user')),
        ];
    }
}
