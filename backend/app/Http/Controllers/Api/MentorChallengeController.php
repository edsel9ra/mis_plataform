<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateChallengeAction;
use App\Actions\CreateChallengeTemplateAction;
use App\Actions\AssignChallengeAction;
use App\Http\Requests\Mentor\StoreChallengeRequest;
use App\Http\Requests\Mentor\UpdateChallengeRequest;
use App\Http\Resources\ChallengeResource;
use App\Models\Challenge;
use App\Models\User;
use App\Services\MentorWorkspaceAccessService;
use App\Services\PlanEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MentorChallengeController
{
    public function __construct(
        private MentorWorkspaceAccessService $access,
        private CreateChallengeAction $createChallenge,
        private CreateChallengeTemplateAction $createTemplate,
        private AssignChallengeAction $assignChallenge,
    ) {}

    public function catalog(Request $request): JsonResponse
    {
        $templates = Challenge::query()
            ->where('mentor_id', $request->user()->id)
            ->whereNull('relationship_id')
            ->whereNull('mentee_id')
            ->where('status', 'template')
            ->with('mediaAssets')
            ->latest()
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return ChallengeResource::collection($templates)->response();
    }

    public function catalogStore(StoreChallengeRequest $request): JsonResponse
    {
        abort_if($request->filled('challenge_id'), 422, 'A challenge template cannot assign another template.');
        $files = $this->files($request);
        $challenge = $this->createTemplate->execute(
            $request->user(),
            $request->validated(),
            $files,
        );

        return response()->json(new ChallengeResource($challenge), 201);
    }

    public function index(Request $request, User $user): JsonResponse
    {
        $authorized = $this->authorizePair($request, $user);
        if (!$this->access->allowsEntitlement($authorized, PlanEntitlementService::CHALLENGES)) {
            return ChallengeResource::collection(collect())->additional([
                'meta' => $this->featureLockMeta($authorized),
            ])->response();
        }

        $query = Challenge::query()
            ->where('relationship_id', $authorized['relationship']->id)
            ->where('mentor_id', $request->user()->id)
            ->where('mentee_id', $user->id)
            ->latest();
        if ($this->access->allowsEntitlement($authorized, PlanEntitlementService::CHALLENGE_MEDIA)) {
            $query->with('mediaAssets');
        }
        $challenges = $query
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return ChallengeResource::collection($challenges)->response();
    }

    public function store(StoreChallengeRequest $request, User $user): JsonResponse
    {
        $authorized = $this->authorizePair($request, $user);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGES);
        $validated = $request->validated();

        if (!empty($validated['challenge_id'])) {
            $template = Challenge::with('mediaAssets')
                ->whereKey($validated['challenge_id'])
                ->where('mentor_id', $request->user()->id)
                ->whereNull('relationship_id')
                ->where('status', 'template')
                ->firstOrFail();

            if ($template->mediaAssets->isNotEmpty()) {
                $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGE_MEDIA);
            }

            $challenge = $this->assignChallenge->execute(
                $request->user(),
                $user,
                $authorized['relationship'],
                $template,
                $validated['due_at'] ?? null,
            );

            return response()->json(new ChallengeResource($challenge), 201);
        }

        $files = $this->files($request);

        if ($files !== []) {
            $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGE_MEDIA);
        }

        Gate::authorize('create', [Challenge::class, $authorized['relationship']]);
        $challenge = $this->createChallenge->execute(
            $request->user(),
            $user,
            $authorized['relationship'],
            $validated,
            $files,
        );

        return response()->json(new ChallengeResource($challenge), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $challenge = Challenge::with(['relationship.source', 'mentor', 'mentee'])->findOrFail($id);
        if ($this->isOwnedTemplate($request->user(), $challenge)) {
            Gate::authorize('view', $challenge);
            $challenge->load('mediaAssets');

            return response()->json(new ChallengeResource($challenge));
        }

        $authorized = $this->access->mentorChallenge($request->user(), $challenge, $this->relationshipId($request));
        $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGES);
        Gate::authorize('view', $challenge);
        $challenge->load('submissions');
        if ($this->access->allowsEntitlement($authorized, PlanEntitlementService::ATTENTION_TRACKING)) {
            $challenge->load(['checkIns' => function ($query) use ($authorized) {
                $sources = ['mentee_self_report'];
                if ($this->access->allowsEntitlement($authorized, PlanEntitlementService::MENTOR_OBSERVATIONS)) {
                    $sources[] = 'mentor_observation';
                }
                $query->whereIn('source', $sources);
            }]);
        }
        if ($this->access->allowsEntitlement($authorized, PlanEntitlementService::CHALLENGE_MEDIA)) {
            $challenge->load(['mediaAssets', 'submissions.mediaAssets']);
        }

        return response()->json(new ChallengeResource($challenge));
    }

    public function update(UpdateChallengeRequest $request, string $id): JsonResponse
    {
        $challenge = Challenge::with(['relationship.source', 'mentor', 'mentee', 'mediaAssets'])->findOrFail($id);
        if ($this->isOwnedTemplate($request->user(), $challenge)) {
            Gate::authorize('manage', $challenge);
            $validated = $request->validated();
            $this->assertValidStatus($challenge, $validated);
            $challenge->update($validated);

            return response()->json(new ChallengeResource($challenge->fresh(['mentor', 'mediaAssets'])));
        }

        $authorized = $this->access->mentorChallenge($request->user(), $challenge, $this->relationshipId($request));
        $this->access->requireEntitlement($authorized, PlanEntitlementService::CHALLENGES);
        Gate::authorize('manage', $challenge);
        $validated = $request->validated();
        $this->assertValidStatus($challenge, $validated);

        if (($validated['status'] ?? null) === 'completed') {
            $validated['completed_at'] = now();
        }

        $challenge->update($validated);

        return response()->json(new ChallengeResource($challenge->fresh(['mentor', 'mentee', 'mediaAssets'])));
    }

    private function authorizePair(Request $request, User $user): array
    {
        return $this->access->mentorPair($request->user(), $user, $this->relationshipId($request));
    }

    private function relationshipId(Request $request): string
    {
        return $request->validate(['relationship_id' => ['required', 'uuid']])['relationship_id'];
    }

    private function isOwnedTemplate(User $mentor, Challenge $challenge): bool
    {
        return $challenge->isTemplate()
            && $challenge->mentor_id === $mentor->id;
    }

    /** @param array<string, mixed> $validated */
    private function assertValidStatus(Challenge $challenge, array $validated): void
    {
        if (!array_key_exists('status', $validated) || $challenge->canTransitionTo($validated['status'])) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => ["A challenge cannot transition from {$challenge->status} to {$validated['status']}."],
        ]);
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

    /** @return array{locked: bool, lock_reason: ?string, plan_status: string} */
    private function featureLockMeta(array $authorized): array
    {
        return [
            'locked' => true,
            'lock_reason' => $authorized['access']['locked']
                ? $authorized['access']['lock_reason']
                : 'entitlement_missing',
            'plan_status' => $authorized['access']['status'],
        ];
    }
}
