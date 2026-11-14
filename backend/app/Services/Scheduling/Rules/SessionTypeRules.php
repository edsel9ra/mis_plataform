<?php

namespace App\Services\Scheduling\Rules;

/**
 * Correspondence between relationship type and the session_type a booking must
 * carry. Single source shared by SessionBookingService and the
 * bookable-relationships endpoint.
 */
final class SessionTypeRules
{
    public static function forRelationshipType(string $relationshipType): ?string
    {
        return match ($relationshipType) {
            'personal' => 'individual',
            'familiar' => 'family',
            'grupal' => 'group',
            'empresa' => 'corporate',
            default => null,
        };
    }
}
