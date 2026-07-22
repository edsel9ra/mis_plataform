<?php

namespace App\Enums;

enum ClientType: string
{
    case Personal = 'personal';
    case Familiar = 'familiar';
    case Grupal = 'grupal';
    case Empresa = 'empresa';

    public function label(): string
    {
        return match ($this) {
            self::Personal => __('client_types.personal'),
            self::Familiar => __('client_types.familiar'),
            self::Grupal => __('client_types.grupal'),
            self::Empresa => __('client_types.empresa'),
        };
    }
}
