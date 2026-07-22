<?php

namespace App\Enums;

enum RelationshipStatus: string
{
    case Matched = 'matched';
    case Pending = 'pending';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Canceled = 'canceled';
}
