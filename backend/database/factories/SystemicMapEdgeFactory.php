<?php

namespace Database\Factories;

use App\Models\SystemicMapEdge;
use App\Models\SystemicMapNode;
use App\Models\SystemicMap;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemicMapEdgeFactory extends Factory
{
    protected $model = SystemicMapEdge::class;

    public function definition(): array
    {
        $from = SystemicMapNode::factory();

        return [
            'from_node_id' => $from,
            'systemic_map_id' => fn (array $attributes) => SystemicMapNode::findOrFail($attributes['from_node_id'])->systemic_map_id,
            'to_node_id' => fn (array $attributes) => SystemicMapNode::factory()->create([
                'systemic_map_id' => $attributes['systemic_map_id'],
            ])->id,
            'label' => fake()->optional()->sentence(2),
            'metadata' => null,
        ];
    }

    public function forMap(SystemicMap $map): static
    {
        return $this->state(function () use ($map): array {
            $nodes = SystemicMapNode::factory()->count(2)->create(['systemic_map_id' => $map->id]);

            return [
                'systemic_map_id' => $map->id,
                'from_node_id' => $nodes[0]->id,
                'to_node_id' => $nodes[1]->id,
            ];
        });
    }
}
