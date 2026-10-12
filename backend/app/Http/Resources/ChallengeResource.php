<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChallengeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $media = $this->relationLoaded('mediaAssets') ? $this->mediaAssets->first() : null;
        $mediaData = $media ? (new MediaAssetResource($media))->resolve($request) : null;
        $mediaUrl = is_array($mediaData) ? ($mediaData['download_url'] ?? null) : null;
        $checkIns = $this->relationLoaded('checkIns')
            ? $this->checkIns
            : null;
        if ($checkIns && !$request->user()?->isMentor()) {
            $checkIns = $checkIns->where('source', 'mentee_self_report')->values();
        }

        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'relationship_id' => $this->relationship_id,
            'mentor_id' => $this->mentor_id,
            'mentee_id' => $this->mentee_id,
            'title' => $this->title,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'objective' => $this->objective,
            'success_criteria' => $this->success_criteria,
            'presentation_format' => $this->presentation_format,
            'frequency' => $this->frequency,
            'progress' => $this->progress,
            'status' => $this->status,
            'due_at' => $this->due_at,
            'assigned_at' => $this->created_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'mentor' => UserSummaryResource::make($this->whenLoaded('mentor')),
            'mentee' => UserSummaryResource::make($this->whenLoaded('mentee')),
            'media' => MediaAssetResource::collection($this->whenLoaded('mediaAssets')),
            'content_url' => $this->when($mediaUrl !== null, $mediaUrl),
            'content_file_url' => $this->when($mediaUrl !== null, $mediaUrl),
            'content_name' => $this->when($mediaUrl !== null, fn () => $media->original_name),
            'content_type' => $this->when($mediaUrl !== null, fn () => $media->mime_type),
            'submissions' => ChallengeSubmissionResource::collection($this->whenLoaded('submissions')),
            'check_ins' => $this->when(
                $checkIns !== null,
                fn () => ChallengeCheckInResource::collection($checkIns),
            ),
        ];
    }
}
