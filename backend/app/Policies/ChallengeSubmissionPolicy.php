<?php

namespace App\Policies;

use App\Models\ChallengeSubmission;
use App\Models\MentorshipRelationship;
use App\Models\User;

class ChallengeSubmissionPolicy
{
    public function view(User $user, ChallengeSubmission $submission): bool
    {
        $challenge = $submission->challenge;
        $relationship = $submission->relationship;

        return $challenge
            && $relationship
            && $relationship->status === 'active'
            && $relationship->type === 'personal'
            && MentorshipRelationship::canonicalSourceType($relationship->source_type) === 'user'
            && $submission->mentee_id === $relationship->source_id
            && ($user->id === $submission->mentee_id || $user->id === $submission->challenge->mentor_id);
    }

    public function create(User $user, ChallengeSubmission $submission): bool
    {
        return $user->id === $submission->mentee_id;
    }
}
