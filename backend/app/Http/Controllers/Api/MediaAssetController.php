<?php

namespace App\Http\Controllers\Api;

use App\Models\MediaAsset;
use App\Models\Challenge;
use App\Models\MentorshipRelationship;
use App\Services\MentorWorkspaceAccessService;
use App\Services\PlanEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MediaAssetController
{
    public function __construct(
        private MentorWorkspaceAccessService $access,
        private PlanEntitlementService $entitlements,
    ) {}

    public function show(Request $request, MediaAsset $media): mixed
    {
        $media->load(['relationship', 'mediaable']);
        Gate::authorize('view', $media);

        if ($media->relationship) {
            abort_unless(
                MentorshipRelationship::canonicalSourceType($media->relationship->source_type) === 'user',
                404,
            );
            if ($request->user()->isMentor()) {
                $authorized = $this->access->mentorRelationship($request->user(), $media->relationship_id);
            } else {
                $authorized = $this->access->menteeRelationship($request->user(), $media->relationship_id);
            }
            $requiredEntitlement = $this->entitlements->mediaEntitlement($media->purpose);
            abort_unless($requiredEntitlement !== null, 404);
            $this->access->requireEntitlement($authorized, $requiredEntitlement);
        } else {
            abort_unless(
                $media->mediaable instanceof Challenge
                    && $media->mediaable->isTemplate()
                    && $media->mediaable->mentor_id === $request->user()->id
                    && $media->uploaded_by === $request->user()->id,
                404,
            );
        }
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->download(
            $media->path,
            $media->original_name,
            ['Content-Type' => $media->mime_type],
        );
    }
}
