<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChallengeSubmissionFactory extends Factory
{
    protected $model = ChallengeSubmission::class;

    public function definition(): array
    {
        return [
            'challenge_id' => Challenge::factory(),
            'relationship_id' => MentorshipRelationship::factory(),
            'mentee_id' => User::factory(),
            'note' => fake()->optional()->paragraph(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ChallengeSubmission $submission): void {
            $challenge = $submission->challenge()->first();
            if (!$challenge) {
                return;
            }

            $submission->forceFill([
                'relationship_id' => $challenge->relationship_id,
                'mentee_id' => $challenge->mentee_id,
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
