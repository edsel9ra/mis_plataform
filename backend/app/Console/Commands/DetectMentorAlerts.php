<?php

namespace App\Console\Commands;

use App\Services\MentorAlertDetectionService;
use Illuminate\Console\Command;

class DetectMentorAlerts extends Command
{
    protected $signature = 'app:detect-mentor-alerts';
    protected $description = 'Create idempotent stagnation and disengagement alerts for mentors';

    public function handle(MentorAlertDetectionService $detector): int
    {
        $created = $detector->detect();
        $this->info("Created {$created} mentor alerts");

        return self::SUCCESS;
    }
}
