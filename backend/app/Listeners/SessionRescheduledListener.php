<?php

namespace App\Listeners;

use App\Events\SessionRescheduled;
use App\Services\Scheduling\MeetAutomationService;
use App\Services\Scheduling\SessionNotificationService;
use Carbon\CarbonImmutable;

class SessionRescheduledListener
{
    public function __construct(
        private SessionNotificationService $notifications,
        private MeetAutomationService $meetAutomation,
    ) {}

    public function handle(SessionRescheduled $event): void
    {
        $this->meetAutomation->synchronizeRescheduled(
            $event->sessionId,
            CarbonImmutable::parse($event->occurredAt),
        );
        $this->notifications->handleRescheduled($event);
    }
}
