<?php

namespace App\Http\Resources;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\FeedbackEntry;
use App\Models\MentorshipRelationship;
use App\Services\PlanEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purpose' => $this->purpose,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'download_url' => $this->when(
                $this->canExposeDownloadUrl($request),
                fn () => route('media.show', ['media' => $this->id], false),
            ),
            'created_at' => $this->created_at,
        ];
    }

    private function canExposeDownloadUrl(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        $relationship = $this->relationLoaded('relationship')
            ? $this->relationship
            : $this->relationship()->first();

        if (!$relationship) {
            if ($this->relationship_id !== null) {
                return false;
            }

            $mediaable = $this->relationLoaded('mediaable')
                ? $this->mediaable
                : $this->mediaable()->first();

            return $user->isMentor()
                && $this->uploaded_by === $user->id
                && $mediaable instanceof Challenge
                && $mediaable->isTemplate()
                && $mediaable->mentor_id === $user->id;
        }

        if ($relationship->status !== 'active'
            || $relationship->type !== 'personal'
            || MentorshipRelationship::canonicalSourceType($relationship->source_type) !== 'user'
            || !in_array($user->id, [$relationship->mentor_id, $relationship->source_id], true)
        ) {
            return false;
        }

        $mediaable = $this->relationLoaded('mediaable')
            ? $this->mediaable
            : $this->mediaable()->first();
        if ($mediaable instanceof Challenge || $mediaable instanceof ChallengeSubmission) {
            if ($mediaable->relationship_id !== $relationship->id) {
                return false;
            }
        }
        if ($mediaable instanceof FeedbackEntry) {
            $thread = $mediaable->relationLoaded('thread')
                ? $mediaable->thread
                : $mediaable->thread()->first();
            if ($thread?->relationship_id !== $relationship->id) {
                return false;
            }
        }

        $requiredEntitlement = app(PlanEntitlementService::class)
            ->mediaEntitlement((string) $this->purpose);
        if ($requiredEntitlement === null) {
            return false;
        }

        $entitlements = app(PlanEntitlementService::class);
        $access = $entitlements->forRelationship($relationship);

        return $entitlements->allowsAccess($access, $requiredEntitlement);
    }
}
