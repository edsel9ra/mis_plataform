<?php

namespace App\Policies;

use App\Models\Challenge;
use App\Models\MentorshipRelationship;
use App\Models\User;

class ChallengePolicy
{
    public function view(User $user, Challenge $challenge): bool
    {
        if ($challenge->status === 'template') {
            return $challenge->isTemplate()
                && $user->isMentor()
                && $challenge->mentor_id === $user->id;
        }

        return $this->pair($user, $challenge);
    }

    public function manage(User $user, Challenge $challenge): bool
    {
        if ($challenge->status === 'template') {
            return $challenge->isTemplate()
                && $user->isMentor()
                && $challenge->mentor_id === $user->id;
        }

        return $user->isMentor()
            && $challenge->mentor_id === $user->id
            && $this->personalRelationship($challenge->relationship);
    }

    public function create(User $user, MentorshipRelationship $relationship): bool
    {
        return $user->isMentor()
            && $relationship->mentor_id === $user->id
            && $this->personalRelationship($relationship);
    }

    private function pair(User $user, Challenge $challenge): bool
    {
        return ($user->isMentor() && $challenge->mentor_id === $user->id
                || $challenge->mentee_id === $user->id)
            && $this->personalRelationship($challenge->relationship);
    }

    private function personalRelationship(?MentorshipRelationship $relationship): bool
    {
        return $relationship?->status === 'active'
            && $relationship->type === 'personal'
            && MentorshipRelationship::canonicalSourceType($relationship->source_type) === 'user'
            && $relationship->source_id === $relationship->source?->id;
    }
}
