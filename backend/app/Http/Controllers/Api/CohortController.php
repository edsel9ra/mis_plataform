<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Cohort\AddCohortMemberRequest;
use App\Http\Requests\Cohort\StoreCohortRequest;
use App\Http\Requests\Cohort\UpdateCohortRequest;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CohortController
{
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

            return $cohort->load('members.user');
        });

        return response()->json($cohort, 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $cohort = Cohort::with(['members.user.personalityAssessment', 'plan'])
            ->findOrFail($id);

        Gate::authorize('view', $cohort);

        return response()->json($cohort);
    }

    public function update(UpdateCohortRequest $request, string $id): JsonResponse
    {
        $cohort = Cohort::findOrFail($id);

        Gate::authorize('manage', $cohort);

        $validated = $request->validated();

        $cohort->update($validated);

        return response()->json($cohort);
    }

    public function addMember(AddCohortMemberRequest $request, string $id): JsonResponse
    {
        $cohort = Cohort::findOrFail($id);

        Gate::authorize('manage', $cohort);

        if ($cohort->members()->count() >= $cohort->max_members) {
            return response()->json(['message' => __('cohorts.max_members_reached')], 422);
        }

        $validated = $request->validated();

        $exists = CohortMember::where('cohort_id', $cohort->id)
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => __('cohorts.member_exists')], 422);
        }

        $member = CohortMember::create([
            'cohort_id' => $cohort->id,
            'user_id' => $validated['user_id'],
            'status' => 'active',
        ]);

        return response()->json($member->load('user'), 201);
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
