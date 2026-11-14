<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge(
            collect($this->resource->attributesToArray())->only([
                'id', 'client_type', 'code', 'name', 'description', 'features', 'entitlements',
                'price_monthly', 'price_yearly', 'max_sessions_per_month', 'max_members',
                'max_mentors', 'is_active', 'subscriptions_count',
                'created_at', 'updated_at', 'deleted_at',
            ])->all(),
            $this->resource->relationsToArray(),
        );
    }
}
