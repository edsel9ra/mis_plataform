<?php

namespace Database\Factories;

use App\Models\MentorshipRelationship;
use Illuminate\Database\Eloquent\Factories\Factory;

class SessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'relationship_id' => MentorshipRelationship::factory(),
            'session_type' => $this->faker->randomElement(['individual', 'family', 'group', 'corporate']),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'scheduled_at' => $this->faker->dateTimeBetween('+1 hour', '+1 month'),
            'duration_minutes' => $this->faker->randomElement([30, 45, 60, 90, 120]),
            'status' => 'scheduled',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => 'completed',
            'scheduled_at' => now()->subDay(),
        ]);
    }
}
