<?php

namespace App\Actions;

use App\Models\EmotionalCheckIn;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CreateEmotionalCheckInAction
{
    public function execute(User $mentee, MentorshipRelationship $relationship, array $data): EmotionalCheckIn
    {
        $checkedInAt = !empty($data['checked_in_at'])
            ? Carbon::parse($data['checked_in_at'])
            : now();

        if ($checkedInAt->isFuture()) {
            throw ValidationException::withMessages([
                'checked_in_at' => ['The check-in timestamp cannot be in the future.'],
            ]);
        }

        return EmotionalCheckIn::create([
            'relationship_id' => $relationship->id,
            'mentee_id' => $mentee->id,
            'mood_score' => $data['mood_score'],
            'stress_score' => $data['stress_score'] ?? null,
            'note' => $data['note'] ?? null,
            'checked_in_at' => $checkedInAt,
        ]);
    }
}
