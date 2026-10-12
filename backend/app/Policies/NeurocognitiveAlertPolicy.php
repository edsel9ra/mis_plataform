<?php

namespace App\Policies;

use App\Models\NeurocognitiveAlert;
use App\Models\MentorshipRelationship;
use App\Models\User;

class NeurocognitiveAlertPolicy
{
    public function view(User $user, NeurocognitiveAlert $alert): bool
    {
        return $user->isMentor()
            && $alert->mentor_id === $user->id
            && $alert->relationship?->status === 'active'
            && $alert->relationship->type === 'personal'
            && MentorshipRelationship::canonicalSourceType($alert->relationship->source_type) === 'user'
            && $alert->relationship->source_id === $alert->mentee_id;
    }

    public function resolve(User $user, NeurocognitiveAlert $alert): bool
    {
        return $this->view($user, $alert);
    }
}
