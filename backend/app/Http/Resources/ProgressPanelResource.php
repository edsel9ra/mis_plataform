<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T-071 — serializes the individual panel and the collective aggregates the
 * subject belongs to (RF-3, RF-11). Block metrics are already normalized
 * arrays produced by the pure calculators.
 */
class ProgressPanelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $panel = (array) $this->resource;

        return [
            'subject' => $panel['subject'] ?? null,
            'viewer_role' => $panel['viewer_role'] ?? 'self',
            'period' => $panel['period'] ?? null,
            'incomplete_blocks' => array_values($panel['incomplete_blocks'] ?? []),

            'objectives' => $panel['objectives'] ?? null,
            'sessions' => $panel['sessions'] ?? null,
            'activities' => $panel['activities'] ?? null,
            'competencies' => $panel['competencies'] ?? null,
            'commitments' => $panel['commitments'] ?? null,

            'collectives' => CollectiveProgressResource::collection($panel['collectives'] ?? []),
        ];
    }
}
