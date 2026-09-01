<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateEmotionalCheckInAction;
use App\Http\Requests\Mentee\StoreEmotionalCheckInRequest;
use App\Http\Resources\EmotionalCheckInResource;
use App\Models\EmotionalCheckIn;
use App\Services\MentorWorkspaceAccessService;
use App\Services\PlanEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenteeEmotionalCheckInController
{
    public function __construct(
        private MentorWorkspaceAccessService $access,
        private CreateEmotionalCheckInAction $createCheckIn,
    ) {}

    public function index(Request $request, string $relationship): JsonResponse
    {
        abort_unless(!$request->user()?->isMentor(), 403);
        $authorized = $this->access->menteeRelationship($request->user(), $relationship);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::EMOTIONAL_CHECK_INS);
        $checkIns = EmotionalCheckIn::query()
            ->where('relationship_id', $relationship)
            ->where('mentee_id', $request->user()->id)
            ->latest('checked_in_at')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return EmotionalCheckInResource::collection($checkIns)->response();
    }

    public function store(StoreEmotionalCheckInRequest $request, string $relationship): JsonResponse
    {
        abort_unless(!$request->user()?->isMentor(), 403);
        $authorized = $this->access->menteeRelationship($request->user(), $relationship);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::EMOTIONAL_CHECK_INS);
        $checkIn = $this->createCheckIn->execute(
            $request->user(),
            $authorized['relationship'],
            $request->validated(),
        );

        return response()->json(new EmotionalCheckInResource($checkIn), 201);
    }
}
