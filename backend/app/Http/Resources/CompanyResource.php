<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'tax_id' => $this->tax_id,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'logo' => $this->logo,
            'industry' => $this->industry,
            'size' => $this->size,
            'is_verified' => $this->is_verified,
            'created_at' => $this->created_at,
            'admin' => UserResource::make($this->whenLoaded('admin')),
            'plan' => PlanResource::make($this->whenLoaded('plan')),
            'employees_count' => $this->whenCounted('employees'),
        ];
    }
}
