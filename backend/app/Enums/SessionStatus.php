<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Canceled = 'canceled';
    case NotCompleted = 'not_completed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Canceled, self::NotCompleted => true,
            self::Scheduled, self::InProgress => false,
        };
    }
}
