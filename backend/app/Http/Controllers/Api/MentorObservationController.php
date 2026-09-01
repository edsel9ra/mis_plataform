<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateNeurocognitiveObservationAction;
use App\Http\Requests\Mentor\StoreObservationRequest;
use App\Http\Resources\NeurocognitiveObservationResource;
use App\Models\Challenge;
use App\Models\NeurocognitiveObservation;
use App\Models\User;
use App\Services\MentorWorkspaceAccessService;
use App\Services\PlanEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MentorObservationController
{
    public function __construct(
        private MentorWorkspaceAccessService $access,
        private CreateNeurocognitiveObservationAction $createObservation,
    ) {}

    public function index(Request $request, User $user): JsonResponse
    {
        $authorized = $this->authorizePair($request, $user);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::ATTENTION_TRACKING);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::MENTOR_OBSERVATIONS);

        $observations = NeurocognitiveObservation::query()
            ->where('relationship_id', $authorized['relationship']->id)
            ->where('mentor_id', $request->user()->id)
            ->latest('observed_at')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return NeurocognitiveObservationResource::collection($observations)->response();
    }

    public function store(StoreObservationRequest $request, User $user): JsonResponse
    {
        $authorized = $this->authorizePair($request, $user);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::ATTENTION_TRACKING);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::MENTOR_OBSERVATIONS);
        $validated = $request->validated();
        $challenge = null;

        if (!empty($validated['challenge_id'])) {
            $challenge = Challenge::findOrFail($validated['challenge_id']);
            $this->access->mentorChallenge($request->user(), $challenge, $authorized['relationship']->id);
        }

        $observation = $this->createObservation->execute(
            $request->user(),
            $user,
            $authorized['relationship'],
            $validated,
            $challenge,
        );
        $observation->load('relationship');
        Gate::authorize('create', $observation);

        return response()->json(new NeurocognitiveObservationResource($observation), 201);
    }

    private function authorizePair(Request $request, User $user): array
    {
        return $this->access->mentorPair(
            $request->user(),
            $user,
            $request->validate(['relationship_id' => ['required', 'uuid']])['relationship_id'],
        );
    }
}
