<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal confirmation of a persisted evidence link (RF-014.4). Only the
 * relation itself is serialized — never source content, answers or scores.
 */
class CompetencyEvidenceLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\CompetencyAssessmentLink $link */
        $link = $this->resource;

        return [
            'id' => $link->id,
            'source_type' => $link->sourceType(),
            'source_id' => $link->assessment_result_id ?? $link->personality_assessment_id,
            'competency_type' => $link->competencyType(),
            'competency_key' => $link->competencyKey(),
            'created_at' => $link->created_at?->toIso8601String(),
            'updated_at' => $link->updated_at?->toIso8601String(),
        ];
    }
}
