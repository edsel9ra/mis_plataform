<?php

namespace App\Actions;

use App\Models\MentorshipRelationship;
use App\Models\Objective;
use App\Models\User;

class ProposeObjectiveAction
{
    /**
     * A mentee proposes an individual objective for themselves. This grants no
     * administration rights: `proposed_by` marks it for the mentor to manage
     * (RF-1, CA-1.5).
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $mentee, array $data): Objective
    {
        $relationship = null;

        if (! empty($data['relationship_id'])) {
            $relationship = MentorshipRelationship::find($data['relationship_id']);
        } else {
            $relationship = MentorshipRelationship::query()
                ->whereIn('source_type', ['user', User::class])
                ->where('source_id', $mentee->id)
                ->where('status', 'active')
                ->latest('started_at')
                ->first();
        }

        return Objective::create([
            'scope' => 'individual',
            'owner_type' => 'user',
            'owner_id' => $mentee->id,
            'relationship_id' => $relationship?->id,
            'created_by' => $mentee->id,
            'proposed_by' => $mentee->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'success_criteria' => $data['success_criteria'],
            'status' => 'not_started',
            'due_at' => $data['due_at'] ?? null,
        ]);
    }
}
