<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'test_version' => $this->test_version,
            'answers' => $this->answers,
            'results' => $this->results,
            'raw_scores' => $this->raw_scores,
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at,
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
