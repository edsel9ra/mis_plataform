<?php

namespace App\Actions;

use App\Models\Objective;
use App\Models\ObjectiveEvidence;
use App\Models\User;

class AttachObjectiveEvidenceAction
{
    /**
     * Attach a note and/or an existing media asset as evidence (RF-1).
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Objective $objective, array $data): ObjectiveEvidence
    {
        return ObjectiveEvidence::create([
            'objective_id' => $objective->id,
            'user_id' => $actor->id,
            'note' => $data['note'] ?? null,
            'media_asset_id' => $data['media_asset_id'] ?? null,
        ]);
    }
}
