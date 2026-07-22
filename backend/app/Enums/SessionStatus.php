<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Canceled = 'canceled';
}
