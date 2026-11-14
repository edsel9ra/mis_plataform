<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge(
            collect($this->resource->attributesToArray())->only([
                'id', 'user_id', 'type', 'dedupe_key', 'status', 'sent_at', 'data', 'read_at',
                'created_at', 'updated_at',
            ])->all(),
            $this->resource->relationsToArray(),
        );
    }
}
