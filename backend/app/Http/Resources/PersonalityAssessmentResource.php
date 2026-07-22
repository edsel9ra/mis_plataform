<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalityAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'test_version' => $this->test_version,
            'completed_at' => $this->completed_at,
            'results' => $this->results,
            'raw_scores' => $this->raw_scores,
        ];
    }
}
