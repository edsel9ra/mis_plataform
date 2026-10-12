<?php

namespace Database\Factories;

use App\Models\MentorshipRelationship;
use App\Models\NeurocognitiveObservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NeurocognitiveObservationFactory extends Factory
{
    protected $model = NeurocognitiveObservation::class;

    public function definition(): array
    {
        return [
            'relationship_id' => MentorshipRelationship::factory(),
            'mentor_id' => User::factory()->mentor(),
            'mentee_id' => User::factory(),
            'challenge_id' => null,
            'attention_score' => fake()->numberBetween(1, 5),
            'engagement_score' => fake()->numberBetween(1, 5),
            'dimensions' => null,
            'note' => fake()->optional()->sentence(),
            'observed_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (NeurocognitiveObservation $observation): void {
            $relationship = $observation->relationship()->first();
            if ($relationship?->source_type !== 'user') {
                return;
            }

            $observation->forceFill([
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
