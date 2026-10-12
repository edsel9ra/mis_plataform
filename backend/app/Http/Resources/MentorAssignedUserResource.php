<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MentorAssignedUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource['user'];
        $assessment = $user->personalityAssessment;
        $factors = collect($assessment?->getOceanScores() ?? [])
            ->only(['O', 'C', 'E', 'A', 'N'])
            ->filter(fn ($value) => is_numeric($value));

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'avatar' => $user->avatar,
            'personality_completed' => (bool) ($assessment?->completed_at && $factors->count() === 5),
            'assignments' => $this->resource['assignments'],
        ];
    }
}
