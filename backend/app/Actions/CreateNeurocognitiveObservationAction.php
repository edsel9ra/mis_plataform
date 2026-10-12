<?php

namespace App\Actions;

use App\Models\Challenge;
use App\Models\MentorshipRelationship;
use App\Models\NeurocognitiveObservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CreateNeurocognitiveObservationAction
{
    public function execute(
        User $mentor,
        User $mentee,
        MentorshipRelationship $relationship,
        array $data,
        ?Challenge $challenge = null,
    ): NeurocognitiveObservation {
        if ($challenge && ($challenge->relationship_id !== $relationship->id || $challenge->mentee_id !== $mentee->id)) {
            abort(404);
        }

        $attentionScore = (int) $data['attention_score'];
        if ($attentionScore > 5) {
            $attentionScore = (int) round($attentionScore / 20, 0, PHP_ROUND_HALF_UP);
        }

        $observedAt = !empty($data['observed_at'])
            ? Carbon::parse($data['observed_at'])
            : now();

        if ($observedAt->isFuture()) {
            throw ValidationException::withMessages([
                'observed_at' => ['The observation timestamp cannot be in the future.'],
            ]);
        }

        return NeurocognitiveObservation::create([
            'relationship_id' => $relationship->id,
            'mentor_id' => $mentor->id,
            'mentee_id' => $mentee->id,
            'challenge_id' => $challenge?->id,
            'attention_score' => max(1, min(5, $attentionScore)),
            'engagement_score' => $data['engagement_score'] ?? null,
            'dimensions' => $data['dimensions'] ?? null,
            'note' => $data['note'] ?? null,
            'observed_at' => $observedAt,
        ]);
    }
}
