<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateChallengeSubmissionAction;
use App\Http\Requests\Mentee\StoreChallengeSubmissionRequest;
use App\Http\Resources\ChallengeResource;
use App\Http\Resources\ChallengeSubmissionResource;
use App\Http\Resources\FeedbackThreadResource;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\FeedbackThread;
use App\Models\User;
use App\Services\MentorWorkspaceAccessService;
use App\Services\PlanEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MenteeChallengeController
{
    public function __construct(
        private MentorWorkspaceAccessService $access,
        private CreateChallengeSubmissionAction $createSubmission,
    ) {}

    public function index(Request $request, string $relationship): JsonResponse
    {
        $this->assertMenteeRoute($request);
        $authorized = $this->access->menteeRelationship($request->user(), $relationship);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGES);
        $query = Challenge::query()
            ->where('relationship_id', $relationship)
            ->where('mentee_id', $request->user()->id);
        if ($this->access->allowsEntitlement($authorized, PlanEntitlementService::CHALLENGE_MEDIA)) {
            $query->with('mediaAssets');
        }
        $challenges = $query
            ->latest()
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return ChallengeResource::collection($challenges)->response();
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->assertMenteeRoute($request);
        $challenge = Challenge::with(['relationship.source', 'mentor', 'mentee'])->findOrFail($id);
        $authorized = $this->access->menteeChallenge($request->user(), $challenge);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGES);
        Gate::authorize('view', $challenge);
        $challenge->load('submissions');
        if ($this->access->allowsEntitlement($authorized, PlanEntitlementService::ATTENTION_TRACKING)) {
            $challenge->load(['checkIns' => fn ($query) => $query->where('source', 'mentee_self_report')]);
        }
        if ($this->access->allowsEntitlement($authorized, PlanEntitlementService::CHALLENGE_MEDIA)) {
            $challenge->load(['mediaAssets', 'submissions.mediaAssets']);
        }

        return response()->json(new ChallengeResource($challenge));
    }

    public function submissions(Request $request, string $challenge): JsonResponse
    {
        $this->assertMenteeRoute($request);
        $challengeModel = Challenge::with('relationship.source')->findOrFail($challenge);
        $authorized = $this->access->menteeChallenge($request->user(), $challengeModel);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGES);
        $query = ChallengeSubmission::query()
            ->where('challenge_id', $challengeModel->id)
            ->where('mentee_id', $request->user()->id);
        if ($this->access->allowsEntitlement($authorized, PlanEntitlementService::CHALLENGE_MEDIA)) {
            $query->with('mediaAssets');
        }
        $submissions = $query
            ->latest('submitted_at')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return ChallengeSubmissionResource::collection($submissions)->response();
    }

    public function storeSubmission(StoreChallengeSubmissionRequest $request, string $challenge): JsonResponse
    {
        $this->assertMenteeRoute($request);
        $challengeModel = Challenge::with('relationship.source')->findOrFail($challenge);
        $authorized = $this->access->menteeChallenge($request->user(), $challengeModel);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGES);
        Gate::authorize('view', $challengeModel);

        if (in_array($challengeModel->status, ['completed', 'canceled'], true)) {
            abort(422, 'This challenge is no longer accepting submissions.');
        }

        $files = $this->files($request);
        if ($files !== []) {
            $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGE_MEDIA);
        }

        $submission = $this->createSubmission->execute(
            $request->user(),
            $authorized['relationship'],
            $challengeModel,
            $request->validated(),
            $files,
        );

        return response()->json(new ChallengeSubmissionResource($submission), 201);
    }

    public function feedback(Request $request, string $challenge): JsonResponse
    {
        $this->assertMenteeRoute($request);
        $challengeModel = Challenge::with('relationship.source')->findOrFail($challenge);
        $authorized = $this->access->menteeChallenge($request->user(), $challengeModel);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGE_FEEDBACK);
        $thread = FeedbackThread::query()
            ->where('relationship_id', $authorized['relationship']->id)
            ->where('challenge_id', $challengeModel->id)
            ->with(['entries.author', 'entries.mediaAssets'])
            ->first();

        return response()->json($thread ? new FeedbackThreadResource($thread) : null);
    }

    /** @return array<int, \Illuminate\Http\UploadedFile> */
    private function files(Request $request): array
    {
        $files = $request->file('files', []);
        $files = is_array($files) ? $files : ($files ? [$files] : []);

        if ($request->hasFile('file')) {
            $files[] = $request->file('file');
        }

        if ($request->hasFile('content_file')) {
            $files[] = $request->file('content_file');
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

    private function assertMenteeRoute(Request $request): void
    {
        abort_unless(!$request->user()?->isMentor(), 403);
    }
}
