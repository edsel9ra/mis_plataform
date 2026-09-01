<?php

namespace Database\Factories;

use App\Models\FeedbackThread;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedbackThreadFactory extends Factory
{
    protected $model = FeedbackThread::class;

    public function definition(): array
    {
        return [
            'relationship_id' => MentorshipRelationship::factory(),
            'challenge_id' => null,
            'mentor_id' => User::factory()->mentor(),
            'mentee_id' => User::factory(),
            'status' => 'open',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (FeedbackThread $thread): void {
            $relationship = $thread->relationship()->first();
            if ($relationship?->source_type !== 'user') {
                return;
            }

            $thread->forceFill([
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
