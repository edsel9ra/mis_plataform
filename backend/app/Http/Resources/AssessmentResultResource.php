<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'assessment_id' => $this->assessment_id,
            'score' => (float) $this->score,
            'passed' => $this->passed,
            'time_spent_seconds' => $this->time_spent_seconds,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'user' => UserSummaryResource::make($this->whenLoaded('user')),
            'assessment' => EvaluationResource::make($this->whenLoaded('assessment')),
        ];
    }
}
