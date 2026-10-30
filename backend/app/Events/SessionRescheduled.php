<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A persisted schedule change published as the contract for downstream consumers. */
class SessionRescheduled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public const TYPE = 'session.rescheduled';

    public function __construct(
        public string $eventId,
        public string $sessionId,
        public string $occurredAt,
        public array $previousSchedule,
        public array $newSchedule,
    ) {}

    public string $type = self::TYPE;
}
