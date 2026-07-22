<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:cleanup-expired-subscriptions')->daily();
Schedule::command('app:send-session-reminders')->everyMinute();
Schedule::command('scout:flush', ['App\\Models\\User'])->daily();
Schedule::command('scout:import', ['App\\Models\\User'])->daily();
Schedule::command('app:sync-pending-match-scores')->hourly();
