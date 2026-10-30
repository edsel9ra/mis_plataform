<?php

namespace App\Policies;

use App\Models\MentorshipRelationship;
use App\Models\MentorAvailabilityException;
use App\Models\MentorAvailabilityWindow;
use App\Models\Session;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SessionPolicy
{
    public const VIEW_SUPPORT_PERMISSION = 'scheduling.view.support';

    public const MANAGE_SUPPORT_PERMISSION = 'scheduling.manage.support';

    public function viewAvailabilitySlots(User $user, User $mentor): Response
    {
        if ($mentor->role !== 'mentor') {
            return Response::denyAsNotFound();
        }

        if ($user->is($mentor) || $this->hasSupportPermission($user, self::MANAGE_SUPPORT_PERMISSION)) {
            return Response::allow();
        }

        $relationships = MentorshipRelationship::query()
            ->active()
            ->where('mentor_id', $mentor->id)
            ->get();

        foreach ($relationships as $relationship) {
            if ($this->canManageRelationship($user, $relationship)) {
                return Response::allow();
            }
        }

        return Response::denyAsNotFound();
    }

    public function manageAvailability(
        User $user,
        User|MentorAvailabilityWindow|MentorAvailabilityException $availability,
    ): Response {
        if ($user->role !== 'mentor') {
            return Response::denyAsNotFound();
        }

        if ($availability instanceof User) {
            return $availability->is($user)
                ? Response::allow()
                : Response::denyAsNotFound();
        }

        return $availability->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function viewAny(User $user): Response
    {
        if ($user->isAdmin() && ! $this->hasSupportPermission($user, self::VIEW_SUPPORT_PERMISSION)) {
            return Response::deny();
        }

        return Response::allow();
    }

    public function create(User $user, MentorshipRelationship $relationship): Response
    {
        if ($user->isAdmin()) {
            if ($this->hasSupportPermission($user, self::MANAGE_SUPPORT_PERMISSION)) {
                return Response::allow();
            }

            return $this->hasSupportPermission($user, self::VIEW_SUPPORT_PERMISSION)
                ? Response::deny()
                : Response::denyAsNotFound();
        }

        if (! $this->canSeeRelationship($user, $relationship)) {
            return Response::denyAsNotFound();
        }

        return $this->canManageRelationship($user, $relationship)
            ? Response::allow()
            : Response::deny();
    }

    public function view(User $user, Session $session): Response
    {
        if ($user->isAdmin()) {
            return $this->hasSupportPermission($user, self::VIEW_SUPPORT_PERMISSION)
                ? Response::allow()
                : Response::denyAsNotFound();
        }

        return $this->canSeeRelationship($user, $this->relationshipFor($session))
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function manage(User $user, Session $session): Response
    {
        if ($user->isAdmin()) {
            if ($this->hasSupportPermission($user, self::MANAGE_SUPPORT_PERMISSION)) {
                return Response::allow();
            }

            return $this->hasSupportPermission($user, self::VIEW_SUPPORT_PERMISSION)
                ? Response::deny()
                : Response::denyAsNotFound();
        }

        $relationship = $this->relationshipFor($session);

        if (! $this->canSeeRelationship($user, $relationship)) {
            return Response::denyAsNotFound();
        }

        return $this->canManageRelationship($user, $relationship)
            ? Response::allow()
            : Response::deny();
    }

    public function manageStatus(User $user, Session $session): Response
    {
        if ($user->isAdmin()) {
            return $this->manage($user, $session);
        }

        $relationship = $this->relationshipFor($session);

        if (! $this->canSeeRelationship($user, $relationship)) {
            return Response::denyAsNotFound();
        }

        return $relationship?->mentor_id === $user->id
            ? Response::allow()
            : Response::deny();
    }

    public function delete(User $user, Session $session): Response
    {
        if ($user->isAdmin()) {
            if ($this->hasSupportPermission($user, self::MANAGE_SUPPORT_PERMISSION)) {
                return Response::allow();
            }

            return $this->hasSupportPermission($user, self::VIEW_SUPPORT_PERMISSION)
                ? Response::deny()
                : Response::denyAsNotFound();
        }

        return $this->canSeeRelationship($user, $this->relationshipFor($session))
            ? Response::deny()
            : Response::denyAsNotFound();
    }

    private function relationshipFor(Session $session): ?MentorshipRelationship
    {
        return $session->relationLoaded('relationship')
            ? $session->relationship
            : $session->relationship()->first();
    }

    private function canSeeRelationship(User $user, ?MentorshipRelationship $relationship): bool
    {
        if (! $relationship) {
            return false;
        }

        return $relationship->involvesUser($user)
            || MentorshipRelationship::userCanManageSource(
                $user,
                $relationship->source_type,
                $relationship->source_id,
            );
    }

    private function canManageRelationship(User $user, MentorshipRelationship $relationship): bool
    {
        if ($relationship->mentor_id === $user->id) {
            return true;
        }

        if (in_array($relationship->source_type, ['user', User::class], true)
            && $relationship->source_id === $user->id) {
            return true;
        }

        return MentorshipRelationship::userCanManageSource(
            $user,
            $relationship->source_type,
            $relationship->source_id,
        );
    }

    private function hasSupportPermission(User $user, string $permission): bool
    {
        return $user->getAllPermissions()->contains('name', $permission);
    }
}
