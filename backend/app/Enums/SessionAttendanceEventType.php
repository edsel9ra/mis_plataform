<?php

namespace App\Enums;

enum SessionAttendanceEventType: string
{
    case Initialization = 'initialization';
    case Confirmation = 'confirmation';
}
