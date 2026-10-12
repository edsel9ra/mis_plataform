<?php

namespace App\Enums;

enum ObjectiveScope: string
{
    case Individual = 'individual';
    case Collective = 'collective';

    public function label(): string
    {
        return match ($this) {
            self::Individual => __('objectives.scopes.individual'),
            self::Collective => __('objectives.scopes.collective'),
        };
    }
}
