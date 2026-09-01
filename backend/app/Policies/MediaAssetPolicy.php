<?php

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\FeedbackEntry;
use App\Models\MentorshipRelationship;
use App\Models\User;

class MediaAssetPolicy
{
    public function view(User $user, MediaAsset $asset): bool
    {
        $relationship = $asset->relationship;

        if (!$relationship) {
            return $asset->relationship_id === null
                && $user->isMentor()
                && $asset->uploaded_by === $user->id
                && $asset->mediaable instanceof Challenge
                && $asset->mediaable->isTemplate()
                && $asset->mediaable->mentor_id === $user->id;
        }

        if ($relationship?->status !== 'active'
            || $relationship->type !== 'personal'
            || MentorshipRelationship::canonicalSourceType($relationship->source_type) !== 'user'
            || !in_array($user->id, [$asset->uploaded_by, $relationship->source_id, $relationship->mentor_id], true)) {
            return false;
        }

        $mediaable = $asset->mediaable;
        if ($mediaable instanceof Challenge || $mediaable instanceof ChallengeSubmission) {
            return $mediaable->relationship_id === $relationship->id;
        }

        if ($mediaable instanceof FeedbackEntry) {
            return $mediaable->thread?->relationship_id === $relationship->id;
        }

        return true;
    }

    public function upload(User $user, MediaAsset $asset): bool
    {
        return $user->isMentor()
            && $asset->uploaded_by === $user->id
            && $this->view($user, $asset);
    }
}
