<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChallengeSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $media = $this->relationLoaded('mediaAssets') ? $this->mediaAssets->first() : null;
        $mediaData = $media ? (new MediaAssetResource($media))->resolve($request) : null;
        $mediaUrl = is_array($mediaData) ? ($mediaData['download_url'] ?? null) : null;

        return [
            'id' => $this->id,
            'challenge_id' => $this->challenge_id,
            'relationship_id' => $this->relationship_id,
            'mentee_id' => $this->mentee_id,
            'note' => $this->note,
            'status' => $this->status,
            'file_url' => $this->when($mediaUrl !== null, $mediaUrl),
            'file_name' => $this->when($mediaUrl !== null, fn () => $media->original_name),
            'mime_type' => $this->when($mediaUrl !== null, fn () => $media->mime_type),
            'submitted_at' => $this->submitted_at,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
            'media' => MediaAssetResource::collection($this->whenLoaded('mediaAssets')),
        ];
    }
}
