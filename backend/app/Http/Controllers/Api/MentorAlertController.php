<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Mentor\ResolveAlertRequest;
use App\Http\Resources\NeurocognitiveAlertResource;
use App\Models\NeurocognitiveAlert;
use App\Models\User;
use App\Services\MentorWorkspaceAccessService;
use App\Services\PlanEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MentorAlertController
{
    public function __construct(
        private MentorWorkspaceAccessService $access,
    ) {}

    public function index(Request $request, User $user): JsonResponse
    {
        $authorized = $this->authorizePair($request, $user);
        if (!$this->access->allowsEntitlement($authorized, PlanEntitlementService::ATTENTION_TRACKING)) {
            return NeurocognitiveAlertResource::collection(collect())->additional([
                'meta' => [
                    'locked' => true,
                    'lock_reason' => $authorized['access']['locked']
                        ? $authorized['access']['lock_reason']
                        : 'entitlement_missing',
                    'plan_status' => $authorized['access']['status'],
                ],
            ])->response();
        }

        $alerts = NeurocognitiveAlert::query()
            ->where('relationship_id', $authorized['relationship']->id)
            ->latest('detected_at')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return NeurocognitiveAlertResource::collection($alerts)->response();
    }

    public function resolve(ResolveAlertRequest $request, string $id): JsonResponse
    {
        $alert = NeurocognitiveAlert::with('relationship')->findOrFail($id);
        $authorized = $this->access->mentorAlert($request->user(), $alert);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::ATTENTION_TRACKING);
        Gate::authorize('resolve', $alert);
        abort_unless($authorized['relationship']->id === $request->validated()['relationship_id'], 404);
        $alert->update(['resolved_at' => now()]);

        return response()->json(new NeurocognitiveAlertResource($alert->fresh()));
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
