<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case NotConfirmed = 'not_confirmed';
    case Attended = 'attended';
    case Absent = 'absent';
}
