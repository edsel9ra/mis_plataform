<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge(
            collect($this->resource->attributesToArray())->only([
                'id', 'name', 'last_name', 'email', 'email_verified_at', 'role', 'client_type',
                'sex', 'birth_date', 'avatar', 'locale', 'timezone', 'is_active', 'company_id',
                'provider', 'provider_id', 'mentor_relationships_count',
                'created_at', 'updated_at', 'deleted_at',
            ])->all(),
            $this->resource->relationsToArray(),
        );
    }
}
