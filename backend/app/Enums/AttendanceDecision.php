<?php

namespace App\Enums;

enum AttendanceDecision: string
{
    case Update = 'update';
    case Keep = 'keep';
    case Reject = 'reject';
}
