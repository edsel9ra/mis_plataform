<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCatalogAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge(
            collect($this->resource->attributesToArray())->only([
                'id', 'module_id', 'slug', 'title', 'short_name', 'description', 'category',
                'client_types', 'questions', 'passing_score', 'time_limit_minutes', 'is_active',
                'metadata', 'results_count', 'created_at', 'updated_at',
            ])->all(),
            $this->resource->relationsToArray(),
        );
    }
}
