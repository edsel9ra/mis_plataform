<?php

namespace App\Actions;

use App\Models\Cohort;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;
use App\Services\Scheduling\Rules\SessionTypeRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Relationships in which the user may book a session: their own active
 * relationships plus active relationships whose source they manage without
 * participating in it (e.g. a cohort they created without being a member —
 * forUser does not cover that manageable-source branch), filtered by
 * SessionPolicy::create — the exact rule POST /sessions applies.
 * Admins get an empty list: this endpoint feeds the booking UI, which they
 * do not use, and forUser would otherwise return every relationship.
 */
class ListBookableRelationshipsAction
{
    /** @return Collection<int, MentorshipRelationship> */
    public function execute(User $user): Collection
    {
        if ($user->isAdmin()) {
            return collect();
        }

        $relationships = MentorshipRelationship::forUser($user)
            ->active()
            ->with(['mentor', 'source'])
            ->get()
            ->keyBy('id');

        // Mirror of the cohort branch of userCanManageSource: cohorts the user
        // created without being a member are bookable but absent from forUser.
        MentorshipRelationship::active()
            ->with(['mentor', 'source'])
            ->whereIn('source_type', ['cohort', Cohort::class])
            ->whereIn('source_id', Cohort::query()->select('id')->where('created_by', $user->id))
            ->get()
            ->each(fn (MentorshipRelationship $relationship) => $relationships->put($relationship->id, $relationship));

        return $relationships
            ->filter(fn (MentorshipRelationship $relationship) => SessionTypeRules::forRelationshipType($relationship->type) !== null
                && Gate::forUser($user)->inspect('create', [Session::class, $relationship])->allowed())
            ->sortBy([['mentor.name', 'asc'], ['mentor.last_name', 'asc'], ['created_at', 'asc']])
            ->values();
    }
}
