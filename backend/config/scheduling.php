<?php

return [
    'default_timezone' => config('app.timezone'),
    'horizons_hours' => [24, 2, 1],
    'immediate_reminder_before_hours' => 24,
    'meet_retry_every_minutes' => 5,
    'min_duration_minutes' => 15,
    'max_duration_minutes' => 480,
    'not_completed_after_hours' => 48,
];
