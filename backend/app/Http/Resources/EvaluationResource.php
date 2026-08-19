<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'short_name' => $this->short_name,
            'description' => $this->description,
            'category' => $this->category,
            'client_types' => $this->client_types,
            'questions' => $this->questions,
            'questions_count' => is_array($this->questions) ? count($this->questions) : 0,
            'passing_score' => $this->passing_score,
            'time_limit_minutes' => $this->time_limit_minutes,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
            'results_count' => $this->whenCounted('results'),
            'user_result' => $this->whenLoaded('results', function () {
                $result = $this->results->first();

                return $result ? AssessmentResultResource::make($result) : null;
            }),
            'created_at' => $this->created_at,
        ];
    }
}
