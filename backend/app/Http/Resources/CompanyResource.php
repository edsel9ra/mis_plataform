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
            'legal_name' => $this->legal_name,
            'tax_id' => $this->tax_id,
            'industry' => $this->industry,
            'size' => $this->size,
            'subscription_status' => $this->subscription_status,
            'logo' => $this->logo,
            'website' => $this->website,
            'phone' => $this->phone,
            'created_at' => $this->created_at,
            'admin' => UserResource::make($this->whenLoaded('admin')),
            'plan' => PlanResource::make($this->whenLoaded('plan')),
            'employees_count' => $this->whenCounted('employees'),
        ];
    }
}
