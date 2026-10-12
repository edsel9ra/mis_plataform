<?php

namespace App\Http\Resources;

use App\Enums\ObjectiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObjectiveResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $objective = $this->resource;

        return [
            'id' => $this->id,
            'scope' => $this->scope->value,
            'scope_label' => $this->scope->label(),
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
            'relationship_id' => $this->relationship_id,
            'title' => $this->title,
            'description' => $this->description,
            'success_criteria' => $this->success_criteria,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'due_at' => $this->due_at?->toIso8601String(),
            'achieved_at' => $this->achieved_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'proposed_by' => $this->proposed_by,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'permissions' => [
                'update' => (bool) $user?->can('update', $objective),
                'transition' => (bool) $user?->can('transition', $objective),
                'add_evidence' => (bool) $user?->can('addEvidence', $objective),
            ],

            'evidences' => ObjectiveEvidenceResource::collection($this->whenLoaded('evidences')),

            'history' => $this->whenLoaded('activities', function () {
                return $this->activities
                    ->sortByDesc('id')
                    ->values()
                    ->map(fn ($activity): array => [
                        'id' => $activity->id,
                        'description' => $activity->description,
                        'old_status' => $activity->properties['old']['status'] ?? null,
                        'new_status' => $activity->properties['attributes']['status'] ?? null,
                        'causer_id' => $activity->causer_id,
                        'causer_name' => $activity->causer?->name,
                        'created_at' => $activity->created_at?->toIso8601String(),
                    ])
                    ->all();
            }),
        ];
    }
}
