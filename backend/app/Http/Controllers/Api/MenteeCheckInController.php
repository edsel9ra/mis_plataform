<?php

namespace App\Http\Controllers\Api;

use App\Actions\RecordChallengeCheckInAction;
use App\Http\Requests\Mentee\StoreChallengeCheckInRequest;
use App\Http\Resources\ChallengeCheckInResource;
use App\Models\Challenge;
use App\Models\ChallengeCheckIn;
use App\Services\MentorWorkspaceAccessService;
use App\Services\PlanEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenteeCheckInController
{
    public function __construct(
        private MentorWorkspaceAccessService $access,
        private RecordChallengeCheckInAction $recordCheckIn,
    ) {}

    public function index(Request $request, string $relationship): JsonResponse
    {
        $this->assertMenteeRoute($request);
        $authorized = $this->authorizeRelationship($request, $relationship);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::ATTENTION_TRACKING);

        $checkIns = ChallengeCheckIn::query()
            ->where('relationship_id', $relationship)
            ->where('mentee_id', $request->user()->id)
            ->where('source', 'mentee_self_report')
            ->latest('checked_in_at')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return ChallengeCheckInResource::collection($checkIns)->response();
    }

    public function store(StoreChallengeCheckInRequest $request, string $relationship): JsonResponse
    {
        $this->assertMenteeRoute($request);
        $authorized = $this->authorizeRelationship($request, $relationship);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::ATTENTION_TRACKING);
        $validated = $request->validated();
        $challenge = null;

        if (!empty($validated['challenge_id'])) {
            $challenge = Challenge::findOrFail($validated['challenge_id']);
            $this->access->menteeChallenge($request->user(), $challenge);
            abort_unless($challenge->relationship_id === $relationship, 404);
        }

        $checkIn = $this->recordCheckIn->execute(
            $request->user(),
            $authorized['relationship'],
            $validated,
            $challenge,
        );

        return response()->json(new ChallengeCheckInResource($checkIn), 201);
    }

    public function challengeIndex(Request $request, string $challenge): JsonResponse
    {
        $this->assertMenteeRoute($request);
        $challengeModel = Challenge::with('relationship.source')->findOrFail($challenge);
        $authorized = $this->access->menteeChallenge($request->user(), $challengeModel);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::ATTENTION_TRACKING);

        $checkIns = ChallengeCheckIn::query()
            ->where('challenge_id', $challengeModel->id)
            ->where('mentee_id', $request->user()->id)
            ->where('source', 'mentee_self_report')
            ->latest('checked_in_at')
            ->get();

        return ChallengeCheckInResource::collection($checkIns)->response();
    }

    public function challengeStore(StoreChallengeCheckInRequest $request, string $challenge): JsonResponse
    {
        $this->assertMenteeRoute($request);
        $challengeModel = Challenge::with('relationship.source')->findOrFail($challenge);
        $authorized = $this->access->menteeChallenge($request->user(), $challengeModel);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::ATTENTION_TRACKING);

        $checkIn = $this->recordCheckIn->execute(
            $request->user(),
            $authorized['relationship'],
            $request->validated(),
            $challengeModel,
        );

        return response()->json(new ChallengeCheckInResource($checkIn), 201);
    }

    private function authorizeRelationship(Request $request, string $relationship): array
    {
        return $this->access->menteeRelationship($request->user(), $relationship);
    }

    private function assertMenteeRoute(Request $request): void
    {
        abort_unless(!$request->user()?->isMentor(), 403);
    }
}
