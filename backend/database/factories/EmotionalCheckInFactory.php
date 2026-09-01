<?php

namespace Database\Factories;

use App\Models\EmotionalCheckIn;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmotionalCheckInFactory extends Factory
{
    protected $model = EmotionalCheckIn::class;

    public function definition(): array
    {
        return [
            'relationship_id' => MentorshipRelationship::factory(),
            'mentee_id' => User::factory(),
            'mood_score' => fake()->numberBetween(1, 5),
            'stress_score' => fake()->numberBetween(1, 5),
            'note' => fake()->optional()->sentence(),
            'checked_in_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (EmotionalCheckIn $checkIn): void {
            $relationship = $checkIn->relationship()->first();
            if ($relationship?->source_type === 'user') {
                $checkIn->forceFill(['mentee_id' => $relationship->source_id])->saveQuietly();
            }
        });
    }

    public function forRelationship(MentorshipRelationship $relationship): static
    {
        return $this->state([
            'relationship_id' => $relationship->id,
            'mentee_id' => $relationship->source_id,
        ]);
    }
}
