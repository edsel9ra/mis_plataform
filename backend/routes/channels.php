<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('relationship.{relationshipId}', function ($user, $relationshipId) {
    $relationship = \App\Models\MentorshipRelationship::find($relationshipId);
    if (!$relationship) return false;

    if ($relationship->involvesUser($user)) {
        return ['id' => $user->id, 'name' => $user->name];
    }

    return false;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return hash_equals((string) $user->id, (string) $userId);
});
