<?php

namespace App\Actions;

use App\Models\MediaAsset;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class StoreMediaAssetAction
{
    public function execute(
        UploadedFile $file,
        User $uploader,
        ?MentorshipRelationship $relationship,
        string $purpose,
        ?Model $mediaable = null,
    ): MediaAsset {
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        $filename = (string) Str::uuid() . ($extension !== '' ? ".{$extension}" : '');
        $directory = $relationship
            ? "mentor/{$relationship->id}"
            : "mentor/catalog/{$uploader->id}";
        $path = $file->storeAs($directory, $filename, 'local');

        if (!$path) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        $asset = new MediaAsset([
            'relationship_id' => $relationship?->id,
            'uploaded_by' => $uploader->id,
            'purpose' => $purpose,
            'disk' => 'local',
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
        ]);

        if ($mediaable) {
            $asset->mediaable()->associate($mediaable);
        }

        $asset->save();

        return $asset;
    }
}
