<?php

namespace App\Listeners;

use App\Events\SessionCanceled;
use App\Services\Scheduling\MeetAutomationService;
use App\Services\Scheduling\SessionNotificationService;
use Carbon\CarbonImmutable;

class SessionCanceledListener
{
    public function __construct(
        private SessionNotificationService $notifications,
        private MeetAutomationService $meetAutomation,
    ) {}

    public function handle(SessionCanceled $event): void
    {
        $this->meetAutomation->deleteCanceled(
            $event->sessionId,
            CarbonImmutable::parse($event->occurredAt),
        );
        $this->notifications->handleCanceled($event);
    }
}
