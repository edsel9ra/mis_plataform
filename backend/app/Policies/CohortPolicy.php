<?php

namespace App\Policies;

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\User;

class CohortPolicy
{
    public function view(User $user, Cohort $cohort): bool
    {
        return $this->manage($user, $cohort)
            || CohortMember::where('cohort_id', $cohort->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();
    }

    public function manage(User $user, Cohort $cohort): bool
    {
        return $user->isAdmin() || $cohort->created_by === $user->id;
    }
}
