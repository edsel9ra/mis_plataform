<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Cohort\AddCohortMemberRequest;
use App\Http\Requests\Cohort\StoreCohortRequest;
use App\Http\Requests\Cohort\UpdateCohortRequest;
use App\Http\Resources\CohortMemberResource;
use App\Http\Resources\CohortResource;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\User;
use App\Services\ContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CohortController
{
    public function __construct(
        private ContentAccessService $contentAccess,
    ) {}

    public function store(StoreCohortRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $cohort = DB::transaction(function () use ($request, $validated) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $user->update(['client_type' => 'grupal']);

            $cohort = Cohort::create([
                'created_by' => $user->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'max_members' => $validated['max_members'] ?? 20,
                'status' => 'pending',
            ]);

            CohortMember::create([
                'cohort_id' => $cohort->id,
                'user_id' => $user->id,
                'status' => 'active',
            ]);

            return $cohort->load(['creator', 'members.user', 'plan']);
        });

        return response()->json(new CohortResource($cohort), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $cohort = Cohort::with(['creator', 'members.user', 'plan'])
            ->findOrFail($id);

        Gate::authorize('view', $cohort);

        return response()->json(new CohortResource($cohort));
    }

    public function update(UpdateCohortRequest $request, string $id): JsonResponse
    {
        $cohort = Cohort::findOrFail($id);

        Gate::authorize('manage', $cohort);

        $validated = $request->validated();

        $cohort->update($validated);

        return response()->json(new CohortResource($cohort->fresh(['creator', 'members.user', 'plan'])));
    }

    public function addMember(AddCohortMemberRequest $request, string $id): JsonResponse
    {
        $cohort = Cohort::findOrFail($id);

        Gate::authorize('manage', $cohort);

        $validated = $request->validated();

        $member = DB::transaction(function () use ($validated, $cohort) {
            $lockedCohort = Cohort::query()->lockForUpdate()->findOrFail($cohort->id);
            $activeMembers = $lockedCohort->members()->where('status', 'active')->count();
            $planLimit = $this->contentAccess
                ->activePlanForSource('cohort', $lockedCohort->id, 'grupal')
                ?->max_members;
            $memberLimit = $planLimit === null
                ? $lockedCohort->max_members
                : min($lockedCohort->max_members, (int) $planLimit);

            if ($activeMembers >= $memberLimit) {
                return null;
            }

            if (CohortMember::where('cohort_id', $lockedCohort->id)
                ->where('user_id', $validated['user_id'])
                ->exists()) {
                return false;
            }

            return CohortMember::create([
                'cohort_id' => $lockedCohort->id,
                'user_id' => $validated['user_id'],
                'status' => 'active',
            ])->load('user');
        });

        if ($member === null) {
            return response()->json(['message' => __('cohorts.max_members_reached')], 422);
        }

        if ($member === false) {
            return response()->json(['message' => __('cohorts.member_exists')], 422);
        }

        return response()->json(new CohortMemberResource($member), 201);
    }

    public function removeMember(Request $request, string $id, string $memberId): JsonResponse
    {
        $cohort = Cohort::findOrFail($id);
        Gate::authorize('manage', $cohort);

        $member = CohortMember::where('cohort_id', $id)
            ->where('id', $memberId)
            ->firstOrFail();

        $member->delete();

        return response()->json(['message' => __('cohorts.member_removed')]);
    }

}
