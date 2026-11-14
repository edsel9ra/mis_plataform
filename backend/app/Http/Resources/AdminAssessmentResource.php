<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge(
            collect($this->resource->attributesToArray())->only([
                'id', 'user_id', 'test_version', 'answers', 'results', 'raw_scores',
                'completed_at', 'created_at', 'updated_at',
            ])->all(),
            $this->resource->relationsToArray(),
        );
    }
}
