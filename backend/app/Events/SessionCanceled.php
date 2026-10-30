<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A persisted cancellation published as the contract for downstream consumers. */
class SessionCanceled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public const TYPE = 'session.canceled';

    public function __construct(
        public string $eventId,
        public string $sessionId,
        public string $actorId,
        public string $occurredAt,
        public string $reason,
        public bool $late,
        public int $leadMinutes,
    ) {}

    public string $type = self::TYPE;
}
