<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_type' => $this->client_type,
            'name' => $this->name,
            'description' => $this->description,
            'price_monthly' => (float) $this->price_monthly,
            'price_yearly' => (float) $this->price_yearly,
            'max_sessions_per_month' => $this->max_sessions_per_month,
            'max_members' => $this->max_members,
            'max_mentors' => $this->max_mentors,
            'features' => $this->features,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'subscriptions_count' => $this->whenCounted('subscriptions'),
        ];
    }
}
