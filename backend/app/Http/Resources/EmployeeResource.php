<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'department' => $this->department,
            'status' => $this->status,
            'invited_at' => $this->invited_at,
            'activated_at' => $this->activated_at,
            'user' => UserResource::make($this->whenLoaded('user')),
            'company' => CompanyResource::make($this->whenLoaded('company')),
        ];
    }
}
