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
            'results' => $this->safeResults(),
        ];
    }

    private function safeResults(): array
    {
        $results = is_array($this->results) ? $this->results : [];

        return array_intersect_key($results, array_flip(['factors', 'facets']));
    }
}
