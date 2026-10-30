<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SessionAttendeeFactory extends Factory
{
    protected $model = SessionAttendee::class;

    public function definition(): array
    {
        return [
            'session_id' => Session::factory(),
            'user_id' => User::factory(),
            'attended' => false,
            'left_at' => null,
            'attendance_status' => AttendanceStatus::NotConfirmed->value,
            'confirmed_by' => null,
            'confirmed_at' => null,
        ];
    }
}
