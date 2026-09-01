<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateFeedbackEntryAction;
use App\Http\Requests\Mentor\StoreFeedbackRequest;
use App\Http\Resources\FeedbackEntryResource;
use App\Http\Resources\FeedbackThreadResource;
use App\Models\Challenge;
use App\Models\FeedbackEntry;
use App\Models\FeedbackThread;
use App\Models\MentorshipRelationship;
use App\Models\User;
use App\Services\MentorWorkspaceAccessService;
use App\Services\PlanEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MentorFeedbackController
{
    public function __construct(
        private MentorWorkspaceAccessService $access,
        private CreateFeedbackEntryAction $createEntry,
        private PlanEntitlementService $entitlements,
    ) {}

    public function all(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'uuid'],
            'relationship_id' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (!empty($validated['user_id']) && !empty($validated['relationship_id'])) {
            $authorized = $this->access->mentorPair(
                $request->user(),
                User::findOrFail($validated['user_id']),
                $validated['relationship_id'],
            );
            $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGE_FEEDBACK);
            $relationshipIds = [$authorized['relationship']->id];
        } elseif (!empty($validated['relationship_id'])) {
            $authorized = $this->access->mentorRelationship($request->user(), $validated['relationship_id']);
            $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGE_FEEDBACK);
            $relationshipIds = [$authorized['relationship']->id];
        } else {
            $relationships = MentorshipRelationship::query()
                ->where('mentor_id', $request->user()->id)
                ->where('status', 'active')
                ->where('type', 'personal')
                ->whereIn('source_type', ['user', User::class])
                ->with('source')
                ->get();
            if (!empty($validated['user_id'])) {
                $relationships = $relationships->filter(
                    fn (MentorshipRelationship $relationship) => $relationship->source_id === $validated['user_id'],
                );
            }
            $relationshipIds = $relationships
                ->filter(function (MentorshipRelationship $relationship): bool {
                    $access = $this->entitlements->forRelationship($relationship);

                    return !$access['locked']
                        && $this->entitlements->allows($access['plan'], PlanEntitlementService::CHALLENGE_FEEDBACK);
                })
                ->pluck('id')
                ->all();
        }

        $entries = FeedbackEntry::query()
            ->whereHas('thread', fn ($query) => $query
                ->where('mentor_id', $request->user()->id)
                ->whereIn('relationship_id', $relationshipIds))
            ->with(['author', 'mediaAssets', 'thread'])
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 20));

        return FeedbackEntryResource::collection($entries)->response();
    }

    public function storeGeneral(StoreFeedbackRequest $request): JsonResponse
    {
        $required = $request->validate([
            'user_id' => ['required', 'uuid'],
            'relationship_id' => ['required', 'uuid'],
        ]);
        $validated = $request->validated();
        $relationshipId = $required['relationship_id'];
        $mentee = User::findOrFail($required['user_id']);
        $authorized = $this->access->mentorPair($request->user(), $mentee, $relationshipId);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGE_FEEDBACK);
        $challenge = null;
        if (!empty($validated['challenge_id'])) {
            $challenge = Challenge::with('relationship.source')->findOrFail($validated['challenge_id']);
            $this->access->mentorChallenge($request->user(), $challenge, $relationshipId);
        }

        $files = $this->files($request);
        if ($files !== []) {
            $this->access->requireEntitlement(
                $authorized,
                $this->entitlements->mediaEntitlement('mentor_feedback'),
            );
        }
        $entry = $this->createEntry->execute(
            $request->user(),
            $mentee,
            $authorized['relationship'],
            $challenge,
            $validated,
            $files,
        );

        return response()->json(new FeedbackEntryResource($entry), 201);
    }

    public function index(Request $request, string $challenge): JsonResponse
    {
        $challengeModel = Challenge::with('relationship.source')->findOrFail($challenge);
        $authorized = $this->access->mentorChallenge($request->user(), $challengeModel, $this->relationshipId($request));
        $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGE_FEEDBACK);
        $thread = FeedbackThread::query()
            ->where('relationship_id', $authorized['relationship']->id)
            ->where('challenge_id', $challengeModel->id)
            ->with(['entries.author', 'entries.mediaAssets'])
            ->first();

        return response()->json($thread ? new FeedbackThreadResource($thread) : null);
    }

    public function store(StoreFeedbackRequest $request, string $challenge): JsonResponse
    {
        $challengeModel = Challenge::with('relationship.source')->findOrFail($challenge);
        $authorized = $this->access->mentorChallenge($request->user(), $challengeModel, $this->relationshipId($request));
        $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGE_FEEDBACK);
        $files = $this->files($request);
        if ($files !== []) {
            $this->access->requireEntitlement(
                $authorized,
                $this->entitlements->mediaEntitlement('mentor_feedback'),
            );
        }

        $entry = $this->createEntry->execute(
            $request->user(),
            $authorized['mentee'],
            $authorized['relationship'],
            $challengeModel,
            $request->validated(),
            $files,
        );

        return response()->json(new FeedbackEntryResource($entry), 201);
    }

    /** @return array<int, \Illuminate\Http\UploadedFile> */
    private function files(Request $request): array
    {
        $files = $request->file('files', []);
        $files = is_array($files) ? $files : ($files ? [$files] : []);

        if ($request->hasFile('file')) {
            $files[] = $request->file('file');
        }

        if ($request->hasFile('feedback_file')) {
            $files[] = $request->file('feedback_file');
        }

        $files = array_values(array_filter($files));
        $maximum = (int) config('mentor.media.max_files', 5);
        if (count($files) > $maximum) {
            throw ValidationException::withMessages([
                'files' => ["You may upload up to {$maximum} files."],
            ]);
        }

        return $files;
    }

    private function relationshipId(Request $request): string
    {
        return $request->validate(['relationship_id' => ['required', 'uuid']])['relationship_id'];
    }
}
