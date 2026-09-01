<?php

namespace App\Http\Controllers\Api;

use App\Actions\StoreMediaAssetAction;
use App\Http\Requests\Mentor\UploadMediaRequest;
use App\Http\Resources\MediaAssetResource;
use App\Models\Challenge;
use App\Models\FeedbackEntry;
use App\Models\MediaAsset;
use App\Services\MentorWorkspaceAccessService;
use App\Services\PlanEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MentorMediaController
{
    public function __construct(
        private MentorWorkspaceAccessService $access,
        private StoreMediaAssetAction $storeMedia,
        private PlanEntitlementService $entitlements,
    ) {}

    public function store(UploadMediaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $authorized = null;
        $mediaable = null;

        if (!empty($validated['relationship_id'])) {
            $authorized = $this->access->mentorRelationship($request->user(), $validated['relationship_id']);
            $requiredEntitlement = $this->entitlements->mediaEntitlement($validated['purpose']);
            abort_unless($requiredEntitlement !== null, 422);
            $this->access->requireEntitlement($authorized, $requiredEntitlement);
        } else {
            abort_unless($validated['purpose'] === 'challenge_content', 422);
        }

        if (!empty($validated['challenge_id'])) {
            $challenge = Challenge::with('relationship.source')->findOrFail($validated['challenge_id']);
            abort_unless($validated['purpose'] === 'challenge_content', 422);
            if ($authorized) {
                $challengeAuthorization = $this->access->mentorChallenge(
                    $request->user(),
                    $challenge,
                    $authorized['relationship']->id,
                );
                $this->access->requireEntitlement($challengeAuthorization, PlanEntitlementService::CHALLENGES);
            } else {
                Gate::authorize('manage', $challenge);
                abort_unless($challenge->isTemplate() && $challenge->mentor_id === $request->user()->id, 404);
            }
            $mediaable = $challenge;
        }

        if (!empty($validated['feedback_entry_id'])) {
            abort_unless($authorized, 422);
            $entry = FeedbackEntry::with('thread.relationship.source')->findOrFail($validated['feedback_entry_id']);
            $feedbackAuthorization = $this->access->mentorFeedback($request->user(), $entry->thread);
            abort_unless($feedbackAuthorization['relationship']->id === $authorized['relationship']->id, 404);
            abort_unless($validated['purpose'] === 'mentor_feedback', 422);
            $mediaable = $entry;
        }

        abort_unless($mediaable, 422);
        $asset = $this->storeMedia->execute(
            $request->file('file'),
            $request->user(),
            $authorized['relationship'] ?? null,
            $validated['purpose'],
            $mediaable,
        )->load('relationship');
        Gate::authorize('upload', $asset);

        return response()->json(new MediaAssetResource($asset), 201);
    }

    public function show(Request $request, MediaAsset $media): mixed
    {
        $media->load(['relationship', 'mediaable']);
        Gate::authorize('view', $media);
        if ($media->relationship) {
            $authorized = $this->access->mentorMedia($request->user(), $media);
            $requiredEntitlement = $this->entitlements->mediaEntitlement($media->purpose);
            abort_unless($requiredEntitlement !== null, 404);
            $this->access->requireEntitlement($authorized, $requiredEntitlement);
        }

        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->download(
            $media->path,
            $media->original_name,
            ['Content-Type' => $media->mime_type],
        );
    }
}
