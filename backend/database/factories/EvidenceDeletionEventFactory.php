<?php

namespace Database\Factories;

use App\Models\EvidenceDeletionEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EvidenceDeletionEventFactory extends Factory
{
    protected $model = EvidenceDeletionEvent::class;

    public function definition(): array
    {
        return [
            'subject_id' => User::factory(),
            'source_type' => fake()->randomElement(['assessment_result', 'personality_assessment']),
            'competency_type' => 'ocean_factor',
            'competency_key' => fake()->randomElement(['O', 'C', 'E', 'A', 'N']),
            'change_type' => EvidenceDeletionEvent::CHANGE_SOURCE_DELETED,
            'actor_pseudonym' => (string) Str::uuid(),
            'occurred_at' => now(),
            'expires_at' => now()->addMonths(12),
        ];
    }
}
