<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\EmotionalCheckInResource;
use App\Models\EmotionalCheckIn;
use App\Models\User;
use App\Services\MentorWorkspaceAccessService;
use App\Services\PlanEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorEmotionalCheckInController
{
    public function __construct(
        private MentorWorkspaceAccessService $access,
    ) {}

    public function index(Request $request, User $user): JsonResponse
    {
        $relationshipId = $request->validate(['relationship_id' => ['required', 'uuid']])['relationship_id'];
        $authorized = $this->access->mentorPair($request->user(), $user, $relationshipId);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::EMOTIONAL_CHECK_INS);
        $checkIns = EmotionalCheckIn::query()
            ->where('relationship_id', $relationshipId)
            ->where('mentee_id', $user->id)
            ->latest('checked_in_at')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return EmotionalCheckInResource::collection($checkIns)->response();
    }
}
