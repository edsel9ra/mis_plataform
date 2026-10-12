<?php

namespace App\Policies;

use App\Models\MentorshipRelationship;
use App\Models\User;

class RelationshipPolicy
{
    public function view(User $user, MentorshipRelationship $relationship): bool
    {
        return $relationship->involvesUser($user);
    }

    public function create(User $user, string $sourceType, string $sourceId): bool
    {
        return MentorshipRelationship::userCanManageSource($user, $sourceType, $sourceId);
    }

    public function updateStatus(User $user, MentorshipRelationship $relationship): bool
    {
        return $relationship->mentor_id === $user->id
            || MentorshipRelationship::userCanManageSource(
                $user,
                $relationship->source_type,
                $relationship->source_id,
            );
    }

    public function delete(User $user, MentorshipRelationship $relationship): bool
    {
        return MentorshipRelationship::userCanManageSource(
            $user,
            $relationship->source_type,
            $relationship->source_id,
        );
    }
}
