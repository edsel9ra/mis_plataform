<?php

namespace App\Policies;

use App\Models\Session;
use App\Models\User;

class SessionPolicy
{
    public function view(User $user, Session $session): bool
    {
        return $session->involvesUser($user);
    }

    public function manage(User $user, Session $session): bool
    {
        $relationship = $session->relationship ?? $session->relationship()->first();

        return $user->isAdmin() || $relationship?->mentor_id === $user->id;
    }
}
