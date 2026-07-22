<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Expired = 'expired';
}
