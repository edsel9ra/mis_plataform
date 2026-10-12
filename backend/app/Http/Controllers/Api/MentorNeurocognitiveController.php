<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ChallengeCheckInResource;
use App\Http\Resources\NeurocognitiveObservationResource;
use App\Models\ChallengeCheckIn;
use App\Models\NeurocognitiveObservation;
use App\Models\User;
use App\Services\MentorWorkspaceAccessService;
use App\Services\PlanEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorNeurocognitiveController
{
    public function __construct(
        private MentorWorkspaceAccessService $access,
    ) {}

    public function show(Request $request, User $user): JsonResponse
    {
        $relationshipId = $request->validate(['relationship_id' => ['required', 'uuid']])['relationship_id'];
        $authorized = $this->access->mentorPair($request->user(), $user, $relationshipId);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::ATTENTION_TRACKING);
        $observationsAllowed = $this->access->allowsEntitlement(
            $authorized,
            PlanEntitlementService::MENTOR_OBSERVATIONS,
        );

        $selfReport = ChallengeCheckIn::query()
            ->where('relationship_id', $relationshipId)
            ->where('mentee_id', $user->id)
            ->where('source', 'mentee_self_report')
            ->latest('checked_in_at')
            ->first();
        $observation = $observationsAllowed
            ? NeurocognitiveObservation::query()
                ->where('relationship_id', $relationshipId)
                ->where('mentor_id', $request->user()->id)
                ->latest('observed_at')
                ->first()
            : null;

        $self = $selfReport ? (new ChallengeCheckInResource($selfReport))->resolve($request) : null;
        $mentorObservation = $observation ? (new NeurocognitiveObservationResource($observation))->resolve($request) : null;

        return response()->json([
            'self_report' => $self,
            'mentee_self_report' => $self,
            'mentor_observation' => $mentorObservation,
            'attention' => [
                'self_report' => $self,
                'mentee_self_report' => $self,
                'mentor_observation' => $mentorObservation,
            ],
            'updated_at' => $observation?->updated_at ?? $selfReport?->updated_at,
        ]);
    }
}
