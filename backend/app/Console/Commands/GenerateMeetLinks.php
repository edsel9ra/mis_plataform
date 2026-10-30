<?php

namespace App\Console\Commands;

use App\Models\Session;
use App\Services\Scheduling\MeetAutomationService;
use App\Services\Scheduling\Rules\MeetAutomationRules;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class GenerateMeetLinks extends Command
{
    protected $signature = 'app:generate-meet-links';

    protected $description = 'Create Google Meet links for future confirmed sessions.';

    public function __construct(private readonly MeetAutomationService $meetAutomationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = CarbonImmutable::now('UTC');
        $sessionIds = Session::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', $now)
            ->pluck('id');

        foreach ($sessionIds as $sessionId) {
            $session = Session::query()->find($sessionId);
            if ($session && MeetAutomationRules::needsScheduleSynchronization($session, $now)) {
                $this->meetAutomationService->synchronizeRescheduled((string) $sessionId, $now);
            } elseif ($session && $session->meet_link === null && $session->meet_event_id === null) {
                $this->meetAutomationService->ensure($session, $now);
            }
        }

        return self::SUCCESS;
    }
}
