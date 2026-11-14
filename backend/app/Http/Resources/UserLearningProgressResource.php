<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserLearningProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge(
            collect($this->resource->attributesToArray())->only([
                'id', 'user_id', 'learning_path_id', 'current_module_id', 'progress', 'status',
                'started_at', 'completed_at', 'created_at', 'updated_at',
            ])->all(),
            $this->resource->relationsToArray(),
        );
    }
}
