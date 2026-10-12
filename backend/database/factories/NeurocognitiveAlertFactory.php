<?php

namespace Database\Factories;

use App\Models\MentorshipRelationship;
use App\Models\NeurocognitiveAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NeurocognitiveAlertFactory extends Factory
{
    protected $model = NeurocognitiveAlert::class;

    public function definition(): array
    {
        $key = (string) Str::uuid();

        return [
            'relationship_id' => MentorshipRelationship::factory(),
            'mentor_id' => User::factory()->mentor(),
            'mentee_id' => User::factory(),
            'type' => 'disengagement',
            'severity' => 'high',
            'dedupe_key' => "test:{$key}",
            'data' => [],
            'detected_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (NeurocognitiveAlert $alert): void {
            $relationship = $alert->relationship()->first();
            if ($relationship?->source_type !== 'user') {
                return;
            }

            $alert->forceFill([
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
