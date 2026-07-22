<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('relationship.{relationshipId}', function ($user, $relationshipId) {
    $relationship = \App\Models\MentorshipRelationship::find($relationshipId);
    if (!$relationship) return false;

    if ($relationship->mentor_id === $user->id) return ['id' => $user->id, 'name' => $user->name];

    if ($relationship->source_type === 'user' && $relationship->source_id === $user->id) {
        return ['id' => $user->id, 'name' => $user->name];
    }

    if ($relationship->source_type === 'company') {
        return $user->company_id === $relationship->source_id
            ? ['id' => $user->id, 'name' => $user->name]
            : false;
    }

    if ($relationship->source_type === 'family_group') {
        return \App\Models\FamilyMember::where('family_group_id', $relationship->source_id)
            ->where('user_id', $user->id)
            ->exists()
            ? ['id' => $user->id, 'name' => $user->name]
            : false;
    }

    if ($relationship->source_type === 'cohort') {
        return \App\Models\CohortMember::where('cohort_id', $relationship->source_id)
            ->where('user_id', $user->id)
            ->exists()
            ? ['id' => $user->id, 'name' => $user->name]
            : false;
    }

    return false;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
