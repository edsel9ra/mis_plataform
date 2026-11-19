<?php

namespace App\Actions;

use App\Models\MediaAsset;
use App\Models\Objective;
use App\Models\ObjectiveEvidence;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AttachObjectiveEvidenceAction
{
    /**
     * Attach a note and/or an existing media asset as evidence (RF-1).
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Objective $objective, array $data): ObjectiveEvidence
    {
        return DB::transaction(function () use ($actor, $objective, $data) {
            $evidence = ObjectiveEvidence::create([
                'objective_id' => $objective->id,
                'user_id' => $actor->id,
                'note' => $data['note'] ?? null,
                'media_asset_id' => $data['media_asset_id'] ?? null,
            ]);

            if (!empty($data['media_asset_id'])) {
                $asset = MediaAsset::findOrFail($data['media_asset_id']);
                $asset->mediaable()->associate($evidence);
                $asset->save();
            }

            return $evidence;
        });
    }
}
