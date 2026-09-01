<?php

namespace Database\Factories;

use App\Models\MentorshipRelationship;
use App\Models\SystemicMap;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemicMapFactory extends Factory
{
    protected $model = SystemicMap::class;

    public function definition(): array
    {
        return [
            'relationship_id' => MentorshipRelationship::factory(),
            'mentor_id' => User::factory()->mentor(),
            'mentee_id' => User::factory(),
            'title' => 'Mapa sistemico',
            'notes' => fake()->optional()->paragraph(),
            'is_private' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (SystemicMap $map): void {
            $relationship = $map->relationship()->first();
            if ($relationship?->source_type !== 'user') {
                return;
            }

            $map->forceFill([
                'mentor_id' => $relationship->mentor_id,
                'mentee_id' => $relationship->source_id,
            ])->saveQuietly();
        });
    }

    public function forRelationship(MentorshipRelationship $relationship): static
    {
        return $this->state([
            'relationship_id' => $relationship->id,
            'mentor_id' => $relationship->mentor_id,
            'mentee_id' => $relationship->source_id,
        ]);
    }
}
