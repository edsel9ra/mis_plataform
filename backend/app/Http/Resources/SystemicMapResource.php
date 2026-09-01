<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemicMapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'relationship_id' => $this->relationship_id,
            'mentor_id' => $this->mentor_id,
            'mentee_id' => $this->mentee_id,
            'title' => $this->title,
            'notes' => $this->notes,
            'is_private' => true,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'nodes' => $this->when($this->relationLoaded('nodes'), fn () => $this->nodes->map(fn ($node) => [
                'id' => $node->id,
                'key' => $node->node_key,
                'label' => $node->label,
                'kind' => $node->kind,
                'position' => $node->position,
                'x' => $node->position['x'] ?? null,
                'y' => $node->position['y'] ?? null,
                'color' => $node->position['color'] ?? null,
                'notes' => $node->notes,
            ])->values()),
            'edges' => $this->when($this->relationLoaded('edges'), fn () => $this->edges->map(fn ($edge) => [
                'id' => $edge->id,
                'from_node_id' => $edge->from_node_id,
                'to_node_id' => $edge->to_node_id,
                'source' => $edge->from_node_id,
                'target' => $edge->to_node_id,
                'label' => $edge->label,
                'metadata' => $edge->metadata,
            ])->values()),
        ];
    }
}
