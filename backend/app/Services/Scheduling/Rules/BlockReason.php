<?php

namespace App\Services\Scheduling\Rules;

/** Shared reason codes returned by pure scheduling blockers. */
final class BlockReason
{
    public const string TERMINAL_STATUS = 'terminal_status';

    public const string ALREADY_STARTED = 'already_started';

    public const string INSUFFICIENT_NOTICE = 'insufficient_notice';

    public const string INVALID_SCHEDULE = 'invalid_schedule';

    public const string NOT_ENDED = 'not_ended';

    public const string WINDOW_CLOSED = 'window_closed';

    public const string NOT_STARTED = 'not_started';

    public const string NOT_REGISTERED = 'not_registered';

    public const string EXCLUDED = 'excluded';

    public const string FORBIDDEN = 'forbidden';

    public const string NOT_CHARGEABLE = 'not_chargeable';

    public const string ALREADY_CONFIRMED_BY_MEMBER = 'already_confirmed_by_member';

    private function __construct() {}
}
