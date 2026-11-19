<?php

namespace App\Actions;

use App\Models\MediaAsset;
use App\Models\Objective;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class StoreObjectiveEvidenceMediaAction
{
    public function execute(UploadedFile $file, User $uploader, Objective $objective): MediaAsset
    {
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        $filename = (string) Str::uuid() . ($extension !== '' ? ".{$extension}" : '');
        $path = $file->storeAs("objectives/{$objective->id}", $filename, 'local');

        if (!$path) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        $asset = new MediaAsset([
            'relationship_id' => $objective->relationship_id,
            'uploaded_by' => $uploader->id,
            'purpose' => 'objective_evidence',
            'disk' => 'local',
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
        ]);

        $asset->mediaable()->associate($objective);
        $asset->save();

        return $asset;
    }
}
