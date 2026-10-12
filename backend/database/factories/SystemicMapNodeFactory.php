<?php

namespace Database\Factories;

use App\Models\SystemicMap;
use App\Models\SystemicMapNode;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemicMapNodeFactory extends Factory
{
    protected $model = SystemicMapNode::class;

    public function definition(): array
    {
        return [
            'systemic_map_id' => SystemicMap::factory(),
            'node_key' => fake()->unique()->slug(2),
            'label' => fake()->words(2, true),
            'kind' => 'person',
            'position' => ['x' => 0, 'y' => 0],
            'notes' => null,
        ];
    }
}
