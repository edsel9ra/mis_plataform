<?php

namespace Database\Factories;

use App\Enums\AvailabilityMode;
use App\Models\MentorAvailabilityException;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MentorAvailabilityExceptionFactory extends Factory
{
    protected $model = MentorAvailabilityException::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->mentor(),
            'mode' => AvailabilityMode::Block,
            'start_date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'end_date' => null,
            'start_time' => null,
            'end_time' => null,
            'timezone' => config('scheduling.default_timezone'),
            'reason' => fake()->optional()->sentence(),
        ];
    }
}
