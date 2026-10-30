<?php

namespace App\Contracts\Scheduling;

/**
 * Required metadata for the append-only rescheduling history implemented by Spec 007.
 */
class SessionScheduleChangeContract
{
    public const FIELDS = [
        'scheduled_at_before',
        'scheduled_at_after',
        'timezone_before',
        'timezone_after',
        'duration_minutes_before',
        'duration_minutes_after',
        'actor_id',
        'occurred_at',
    ];

    public const OPTIONAL_FIELDS = [
        'reason',
    ];
}
