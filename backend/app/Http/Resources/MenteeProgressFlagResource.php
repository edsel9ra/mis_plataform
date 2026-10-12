<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenteeProgressFlagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'mentee' => $this->resource['mentee'] ?? null,
            'relationship_id' => $this->resource['relationship_id'] ?? null,
            'objectives_overdue' => $this->resource['objectives_overdue'] ?? 0,
            'objectives_pending' => $this->resource['objectives_pending'] ?? 0,
            'activities_pending' => $this->resource['activities_pending'] ?? 0,
            'no_activity' => (bool) ($this->resource['no_activity'] ?? false),
            'last_activity_at' => $this->resource['last_activity_at'] ?? null,
            'workspace_locked' => (bool) ($this->resource['workspace_locked'] ?? false),
        ];
    }
}
