<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\ChallengeCheckIn;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChallengeCheckInFactory extends Factory
{
    protected $model = ChallengeCheckIn::class;

    public function definition(): array
    {
        return [
            'challenge_id' => Challenge::factory(),
            'relationship_id' => MentorshipRelationship::factory(),
            'mentee_id' => User::factory(),
            'author_id' => null,
            'source' => 'mentee_self_report',
            'status' => null,
            'attention_score' => fake()->numberBetween(1, 5),
            'note' => fake()->optional()->sentence(),
            'checked_in_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ChallengeCheckIn $checkIn): void {
            $challenge = $checkIn->challenge()->first();
            if (!$challenge) {
                return;
            }

            $checkIn->forceFill([
                'relationship_id' => $challenge->relationship_id,
                'mentee_id' => $challenge->mentee_id,
                'author_id' => $checkIn->source === 'mentee_self_report'
                    ? $challenge->mentee_id
                    : $checkIn->author_id,
            ])->saveQuietly();
        });
    }

    public function forChallenge(Challenge $challenge): static
    {
        return $this->state([
            'challenge_id' => $challenge->id,
            'relationship_id' => $challenge->relationship_id,
            'mentee_id' => $challenge->mentee_id,
        ]);
    }
}
