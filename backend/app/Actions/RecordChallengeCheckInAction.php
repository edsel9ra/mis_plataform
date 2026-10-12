<?php

namespace App\Actions;

use App\Models\Challenge;
use App\Models\ChallengeCheckIn;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class RecordChallengeCheckInAction
{
    public function execute(
        User $mentee,
        MentorshipRelationship $relationship,
        array $data,
        ?Challenge $challenge = null,
    ): ChallengeCheckIn {
        if ($challenge && ($challenge->relationship_id !== $relationship->id || $challenge->mentee_id !== $mentee->id)) {
            abort(404);
        }

        $checkedInAt = $this->checkedInAt($data);

        return ChallengeCheckIn::create([
            'challenge_id' => $challenge?->id ?? ($data['challenge_id'] ?? null),
            'relationship_id' => $relationship->id,
            'mentee_id' => $mentee->id,
            'author_id' => $mentee->id,
            'source' => 'mentee_self_report',
            'attention_score' => $data['attention_score'],
            'status' => $data['status'] ?? null,
            'note' => $data['note'] ?? null,
            'checked_in_at' => $checkedInAt,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function checkedInAt(array $data): Carbon
    {
        $checkedInAt = !empty($data['checked_in_at'])
            ? Carbon::parse($data['checked_in_at'])
            : now();

        if ($checkedInAt->isFuture()) {
            throw ValidationException::withMessages([
                'checked_in_at' => ['The check-in timestamp cannot be in the future.'],
            ]);
        }

        return $checkedInAt;
    }
}
