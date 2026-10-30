<?php

namespace Database\Factories;

use App\Models\MentorAvailabilityWindow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MentorAvailabilityWindowFactory extends Factory
{
    protected $model = MentorAvailabilityWindow::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->mentor(),
            'day_of_week' => fake()->numberBetween(1, 5),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'timezone' => config('scheduling.default_timezone'),
        ];
    }
}
