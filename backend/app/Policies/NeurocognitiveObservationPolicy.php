<?php

namespace App\Policies;

use App\Models\NeurocognitiveObservation;
use App\Models\MentorshipRelationship;
use App\Models\User;

class NeurocognitiveObservationPolicy
{
    public function view(User $user, NeurocognitiveObservation $observation): bool
    {
        $relationship = $observation->relationship;

        return $user->isMentor()
            && $observation->mentor_id === $user->id
            && $relationship?->status === 'active'
            && $relationship->type === 'personal'
            && MentorshipRelationship::canonicalSourceType($relationship->source_type) === 'user'
            && $relationship->source_id === $observation->mentee_id;
    }

    public function create(User $user, NeurocognitiveObservation $observation): bool
    {
        return $this->view($user, $observation);
    }
}
