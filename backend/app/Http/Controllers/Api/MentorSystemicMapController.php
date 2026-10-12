<?php

namespace App\Http\Controllers\Api;

use App\Actions\UpsertSystemicMapAction;
use App\Http\Requests\Mentor\UpsertSystemicMapRequest;
use App\Http\Resources\SystemicMapResource;
use App\Models\SystemicMap;
use App\Models\User;
use App\Services\MentorWorkspaceAccessService;
use App\Services\PlanEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MentorSystemicMapController
{
    public function __construct(
        private MentorWorkspaceAccessService $access,
        private UpsertSystemicMapAction $upsertMap,
    ) {}

    public function show(Request $request, User $user): JsonResponse
    {
        $authorized = $this->authorizePair($request, $user);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::SYSTEMIC_MAPS);
        $map = SystemicMap::query()
            ->where('relationship_id', $authorized['relationship']->id)
            ->where('mentor_id', $request->user()->id)
            ->with(['relationship', 'nodes', 'edges'])
            ->first();

        if (!$map) {
            return response()->json(null);
        }

        Gate::authorize('view', $map);

        return response()->json(new SystemicMapResource($map));
    }

    public function update(UpsertSystemicMapRequest $request, User $user): JsonResponse
    {
        $authorized = $this->authorizePair($request, $user);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::SYSTEMIC_MAPS);
        $map = $this->upsertMap->execute(
            $request->user(),
            $user,
            $authorized['relationship'],
            $request->validated(),
        );
        Gate::authorize('manage', $map->load('relationship'));

        return response()->json(new SystemicMapResource($map));
    }

    public function showById(Request $request, string $id): JsonResponse
    {
        $map = SystemicMap::with(['relationship.source', 'nodes', 'edges'])->findOrFail($id);
        $authorized = $this->access->mentorMap($request->user(), $map);
        $this->access->requireEntitlement($authorized, PlanEntitlementService::SYSTEMIC_MAPS);
        Gate::authorize('view', $map);

        return response()->json(new SystemicMapResource($map));
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
