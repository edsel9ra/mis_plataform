<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:cleanup-expired-subscriptions')->daily();
Schedule::command('app:send-session-reminders')->everyMinute();
Schedule::command('app:advance-session-lifecycle')->everyMinute();
Schedule::command('app:generate-meet-links')
    ->cron('*/'.max(1, (int) config('scheduling.meet_retry_every_minutes', 5)).' * * * *')
    ->withoutOverlapping();
Schedule::command('scout:flush', ['App\\Models\\User'])->daily();
Schedule::command('scout:import', ['App\\Models\\User'])->daily();
Schedule::command('app:sync-pending-match-scores')->hourly();
Schedule::command('app:detect-mentor-alerts')->hourly();
