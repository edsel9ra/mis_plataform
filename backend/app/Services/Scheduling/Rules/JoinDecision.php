<?php

namespace App\Services\Scheduling\Rules;

enum JoinDecision: string
{
    case Join = 'join';
    case ExcludedForSession = 'excluded_for_session';
    case NotEligible = 'not_eligible';
}
