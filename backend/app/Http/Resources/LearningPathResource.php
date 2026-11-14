<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearningPathResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge(
            collect($this->resource->attributesToArray())->only([
                'id', 'title', 'description', 'personality_tags', 'client_type', 'level',
                'estimated_hours', 'is_active', 'created_at', 'updated_at', 'deleted_at',
            ])->all(),
            $this->resource->relationsToArray(),
        );
    }
}
