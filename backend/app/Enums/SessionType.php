<?php

namespace App\Enums;

enum SessionType: string
{
    case Individual = 'individual';
    case Family = 'family';
    case Group = 'group';
    case Corporate = 'corporate';
}
