<?php

namespace App\Actions;

use App\Models\Challenge;
use App\Models\ChallengeCheckIn;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class RecordMentorChallengeCheckInAction
{
    public function execute(
        User $mentor,
        User $mentee,
        MentorshipRelationship $relationship,
        array $data,
        Challenge $challenge,
    ): ChallengeCheckIn {
        abort_unless(
            $challenge->relationship_id === $relationship->id
                && $challenge->mentor_id === $mentor->id
                && $challenge->mentee_id === $mentee->id,
            404,
        );

        $checkedInAt = !empty($data['checked_in_at'])
            ? Carbon::parse($data['checked_in_at'])
            : now();

        if ($checkedInAt->isFuture()) {
            throw ValidationException::withMessages([
                'checked_in_at' => ['The check-in timestamp cannot be in the future.'],
            ]);
        }

        return ChallengeCheckIn::create([
            'challenge_id' => $challenge->id,
            'relationship_id' => $relationship->id,
            'mentee_id' => $mentee->id,
            'author_id' => $mentor->id,
            'source' => 'mentor_observation',
            'status' => $data['status'] ?? null,
            'attention_score' => $data['attention_score'] ?? null,
            'note' => $data['note'] ?? null,
            'checked_in_at' => $checkedInAt,
        ]);
    }
}
