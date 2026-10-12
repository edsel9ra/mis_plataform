<?php

namespace App\Policies;

use App\Models\FeedbackThread;
use App\Models\MentorshipRelationship;
use App\Models\User;

class FeedbackThreadPolicy
{
    public function view(User $user, FeedbackThread $thread): bool
    {
        return $this->pair($user, $thread)
            && in_array($user->id, [$thread->mentor_id, $thread->mentee_id], true);
    }

    public function manage(User $user, FeedbackThread $thread): bool
    {
        return $user->isMentor()
            && $thread->mentor_id === $user->id
            && $this->pair($user, $thread);
    }

    private function pair(User $user, FeedbackThread $thread): bool
    {
        $relationship = $thread->relationship;

        return $relationship?->status === 'active'
            && $relationship->type === 'personal'
            && MentorshipRelationship::canonicalSourceType($relationship->source_type) === 'user'
            && $relationship->source_id === $thread->mentee_id
            && in_array($user->id, [$thread->mentor_id, $thread->mentee_id], true);
    }
}
