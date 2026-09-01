<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChallengeFactory extends Factory
{
    protected $model = Challenge::class;

    public function definition(): array
    {
        return [
            'relationship_id' => MentorshipRelationship::factory(),
            'mentor_id' => User::factory()->mentor(),
            'mentee_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'instructions' => fake()->optional()->paragraph(),
            'objective' => fake()->optional()->sentence(),
            'success_criteria' => fake()->optional()->sentence(),
            'presentation_format' => fake()->optional()->randomElement(['individual', 'written', 'audio']),
            'frequency' => fake()->optional()->randomElement(['daily', 'weekly']),
            'progress' => fake()->numberBetween(0, 100),
            'status' => 'assigned',
            'due_at' => now()->addDays(7),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Challenge $challenge): void {
            $relationship = $challenge->relationship()->first();
            if ($relationship?->source_type !== 'user') {
                return;
            }

            $challenge->forceFill([
                'mentor_id' => $relationship->mentor_id,
                'mentee_id' => $relationship->source_id,
            ])->saveQuietly();
        });
    }

    public function template(): static
    {
        return $this->state([
            'relationship_id' => null,
            'mentee_id' => null,
            'status' => 'template',
        ]);
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
