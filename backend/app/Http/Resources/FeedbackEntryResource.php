<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedbackEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $media = $this->relationLoaded('mediaAssets') ? $this->mediaAssets->first() : null;
        $mediaData = $media ? (new MediaAssetResource($media))->resolve($request) : null;
        $mediaUrl = is_array($mediaData) ? ($mediaData['download_url'] ?? null) : null;

        return [
            'id' => $this->id,
            'feedback_thread_id' => $this->feedback_thread_id,
            'author_id' => $this->author_id,
            'user_id' => $this->thread?->mentee_id,
            'body' => $this->body,
            'note' => $this->body,
            'created_at' => $this->created_at,
            'author' => UserSummaryResource::make($this->whenLoaded('author')),
            'media' => MediaAssetResource::collection($this->whenLoaded('mediaAssets')),
            'file_url' => $this->when($mediaUrl !== null, $mediaUrl),
            'media_url' => $this->when($mediaUrl !== null, $mediaUrl),
            'file_name' => $this->when($mediaUrl !== null, fn () => $media->original_name),
            'mime_type' => $this->when($mediaUrl !== null, fn () => $media->mime_type),
        ];
    }
}
