<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MentorshipRelationshipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['personal', 'familiar', 'grupal', 'corporate']),
            'source_type' => 'user',
            'source_id' => User::factory(),
            'mentor_id' => User::factory()->mentor(),
            'status' => 'active',
            'objectives' => $this->faker->sentence(),
            'started_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => 'pending',
            'started_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
