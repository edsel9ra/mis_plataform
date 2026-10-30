<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Enums\SessionAttendanceEventType;
use App\Models\SessionAttendanceEvent;
use App\Models\SessionAttendee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SessionAttendanceEventFactory extends Factory
{
    protected $model = SessionAttendanceEvent::class;

    public function definition(): array
    {
        return [
            'session_attendee_id' => SessionAttendee::factory(),
            'event_type' => SessionAttendanceEventType::Confirmation->value,
            'attendance_status' => AttendanceStatus::Attended->value,
            'recorded_by' => User::factory(),
            'occurred_at' => now(),
        ];
    }

    public function initialization(): static
    {
        return $this->state(fn (): array => [
            'event_type' => SessionAttendanceEventType::Initialization->value,
            'attendance_status' => AttendanceStatus::NotConfirmed->value,
            'recorded_by' => null,
        ]);
    }

    public function confirmation(): static
    {
        return $this->state(fn (): array => [
            'event_type' => SessionAttendanceEventType::Confirmation->value,
            'attendance_status' => AttendanceStatus::Attended->value,
            'recorded_by' => User::factory(),
        ]);
    }
}
