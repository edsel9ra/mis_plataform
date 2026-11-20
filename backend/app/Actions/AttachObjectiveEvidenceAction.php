<?php

namespace App\Actions;

use App\Models\MediaAsset;
use App\Models\Objective;
use App\Models\ObjectiveEvidence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            $asset = null;
            if (! empty($data['media_asset_id'])) {
                // Lock the row and re-check the request conditions: the
                // upload may have been deleted, re-linked or purged between
                // validation and this transaction.
                $asset = MediaAsset::whereKey($data['media_asset_id'])
                    ->lockForUpdate()
                    ->first();

                if ($asset === null || ! $asset->isAttachableAsEvidence($objective, $actor)) {
                    throw ValidationException::withMessages([
                        'media_asset_id' => __('progress.messages.invalid_media_asset'),
                    ]);
                }
            }

            $evidence = ObjectiveEvidence::create([
                'objective_id' => $objective->id,
                'user_id' => $actor->id,
                'note' => $data['note'] ?? null,
                'media_asset_id' => $data['media_asset_id'] ?? null,
            ]);

            if ($asset !== null) {
                $asset->mediaable()->associate($evidence);
                $asset->save();
            }

            return $evidence;
        });
    }
}
