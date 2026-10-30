<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionAttendeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => UserSummaryResource::make($this->whenLoaded('user')),
            'attendance_status' => $this->attendance_status?->value,
            'attended' => $this->attended,
            'confirmed_by' => $this->confirmed_by,
            'confirmed_at' => $this->confirmed_at,
            'left_at' => $this->left_at,
            'joined_at' => $this->joined_at,
        ];
    }
}
