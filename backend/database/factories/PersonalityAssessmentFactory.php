<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonalityAssessmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'test_version' => 'ipip-neo-120',
            'answers' => [],
            'results' => [
                'factors' => [
                    'O' => $this->faker->numberBetween(20, 95),
                    'C' => $this->faker->numberBetween(20, 95),
                    'E' => $this->faker->numberBetween(20, 95),
                    'A' => $this->faker->numberBetween(20, 95),
                    'N' => $this->faker->numberBetween(20, 95),
                ],
            ],
            'raw_scores' => [],
            'completed_at' => now(),
        ];
    }
}
