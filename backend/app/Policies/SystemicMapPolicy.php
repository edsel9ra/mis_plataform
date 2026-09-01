<?php

namespace App\Policies;

use App\Models\SystemicMap;
use App\Models\MentorshipRelationship;
use App\Models\User;

class SystemicMapPolicy
{
    public function view(User $user, SystemicMap $map): bool
    {
        return $user->isMentor()
            && $map->mentor_id === $user->id
            && $map->is_private
            && $map->relationship?->status === 'active'
            && $map->relationship->type === 'personal'
            && MentorshipRelationship::canonicalSourceType($map->relationship->source_type) === 'user'
            && $map->relationship->source_id === $map->mentee_id;
    }

    public function manage(User $user, SystemicMap $map): bool
    {
        return $this->view($user, $map);
    }
}
