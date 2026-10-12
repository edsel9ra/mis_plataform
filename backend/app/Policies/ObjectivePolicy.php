<?php

namespace App\Policies;

use App\Enums\ObjectiveScope;
use App\Models\MentorshipRelationship;
use App\Models\Objective;
use App\Models\User;

class ObjectivePolicy
{
    public const SUPPORT_PERMISSION = 'progress.view.support';

    public function view(User $user, Objective $objective): bool
    {
        if ($this->isSupport($user)) {
            return true;
        }

        return $this->isTitular($user, $objective)
            || $this->isAssignedMentor($user, $objective)
            || $this->isCollectiveMember($user, $objective);
    }

    public function update(User $user, Objective $objective): bool
    {
        return $this->manage($user, $objective);
    }

    public function transition(User $user, Objective $objective): bool
    {
        return $this->manage($user, $objective);
    }

    public function addEvidence(User $user, Objective $objective): bool
    {
        if ($this->isSupport($user)) {
            return true;
        }

        if ($objective->scope === ObjectiveScope::Individual) {
            return $this->isTitular($user, $objective)
                || $this->isAssignedMentor($user, $objective);
        }

        return $this->isCollectiveMember($user, $objective)
            || $this->isCollectiveManager($user, $objective);
    }

    /**
     * A mentee may propose an individual objective without gaining any
     * administration rights over it (RF-1 CA-1.5).
     */
    public function propose(User $user, mixed $objective = null): bool
    {
        return (bool) $user->is_active;
    }

    public function createIndividual(User $user, string $titularId, ?MentorshipRelationship $relationship = null): bool
    {
        if ($this->isSupport($user)) {
            return true;
        }

        if ($relationship) {
            return $relationship->mentor_id === $user->id
                && $relationship->status === 'active'
                && MentorshipRelationship::canonicalSourceType($relationship->source_type) === 'user'
                && $relationship->source_id === $titularId;
        }

        return MentorshipRelationship::query()
            ->where('mentor_id', $user->id)
            ->where('status', 'active')
            ->whereIn('source_type', ['user', User::class])
            ->where('source_id', $titularId)
            ->exists();
    }

    public function createCollective(User $user, string $ownerType, string $ownerId): bool
    {
        return $this->isCollectiveManager($user, (object) [
            'scope' => ObjectiveScope::Collective,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
        ]);
    }

    public function manage(User $user, Objective $objective): bool
    {
        if ($this->isSupport($user)) {
            return true;
        }

        if ($objective->scope === ObjectiveScope::Collective) {
            return $this->isCollectiveManager($user, $objective);
        }

        return $this->isAssignedMentor($user, $objective);
    }

    public function isSupport(User $user): bool
    {
        return $user->getAllPermissions()->contains('name', self::SUPPORT_PERMISSION);
    }

    private function isTitular(User $user, Objective $objective): bool
    {
        return $objective->scope === ObjectiveScope::Individual
            && $objective->owner_type === 'user'
            && $objective->owner_id === $user->id;
    }

    private function isAssignedMentor(User $user, Objective $objective): bool
    {
        if ($objective->scope !== ObjectiveScope::Individual || $objective->owner_type !== 'user') {
            return false;
        }

        $relationship = $objective->relationLoaded('relationship')
            ? $objective->relationship
            : $objective->relationship()->first();

        return $relationship !== null
            && $relationship->mentor_id === $user->id
            && $relationship->status === 'active'
            && MentorshipRelationship::canonicalSourceType($relationship->source_type) === 'user'
            && $relationship->source_id === $objective->owner_id;
    }

    private function isCollectiveManager(User $user, Objective $objective): bool
    {
        if ($objective->scope !== ObjectiveScope::Collective) {
            return false;
        }

        // Admins only act with the explicit support permission (D11, RF-12).
        if ($user->isAdmin()) {
            return false;
        }

        return MentorshipRelationship::userCanManageSource($user, (string) $objective->owner_type, (string) $objective->owner_id);
    }

    private function isCollectiveMember(User $user, Objective $objective): bool
    {
        if ($objective->scope !== ObjectiveScope::Collective) {
            return false;
        }

        if ($user->isAdmin()) {
            return false;
        }

        return MentorshipRelationship::userCanAccessSource($user, (string) $objective->owner_type, (string) $objective->owner_id);
    }
}
