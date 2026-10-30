<?php

namespace App\Actions;

use App\Services\Scheduling\SessionBookingService;
use App\Services\Scheduling\SessionNotificationService;
use App\Models\Session;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class CreateSessionAction
{
    public function __construct(
        private SessionBookingService $sessionBookingService,
        private SessionNotificationService $sessionNotificationService,
    ) {}

    public function execute(array $data, User $actor): Session
    {
        $session = $this->sessionBookingService->validateAndCreate($data, $actor, Carbon::now());

        // SessionBookingService returns only after its transaction commits, so the
        // immediate notice cannot refer to an uncommitted booking.
        $this->sessionNotificationService->handleCreated(
            (string) $session->getKey(),
            CarbonImmutable::now('UTC'),
        );

        return $session;
    }
}
