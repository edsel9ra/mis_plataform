<?php

namespace App\Actions;

use App\Models\MentorshipRelationship;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateRelationshipAction
{
    public function execute(array $data): MentorshipRelationship
    {
        $mentor = User::findOrFail($data['mentor_id']);
        if ($mentor->role !== 'mentor') {
            throw ValidationException::withMessages([
                'mentor_id' => [__('validation.mentor_invalid')],
            ]);
        }

        $existing = MentorshipRelationship::where([
            'source_type' => $data['source_type'],
            'source_id' => $data['source_id'],
            'mentor_id' => $data['mentor_id'],
        ])->whereIn('status', ['pending', 'active'])->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'relationship' => [__('validation.relationship_exists')],
            ]);
        }

        $relationship = MentorshipRelationship::create([
            'type' => $data['type'],
            'source_type' => $data['source_type'],
            'source_id' => $data['source_id'],
            'mentor_id' => $data['mentor_id'],
            'objectives' => $data['objectives'] ?? null,
            'status' => 'pending',
        ]);

        $relationship->load(['mentor', 'source']);

        return $relationship;
    }
}
