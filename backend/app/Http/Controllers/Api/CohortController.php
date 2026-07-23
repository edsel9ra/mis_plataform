<?php

namespace App\Http\Controllers\Api;

use App\Models\Cohort;
use App\Models\CohortMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CohortController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'max_members' => ['sometimes', 'integer', 'min:2', 'max:100'],
        ]);

        $user = $request->user();
        $user->update(['client_type' => 'grupal']);

        $cohort = Cohort::create([
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

        return response()->json($cohort->load('members.user'), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $cohort = Cohort::with(['members.user.personalityAssessment', 'plan'])
            ->findOrFail($id);

        abort_unless($this->canAccessCohort($request, $cohort), 403);

        return response()->json($cohort);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $cohort = Cohort::findOrFail($id);

        abort_unless($this->canAccessCohort($request, $cohort), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'max_members' => ['sometimes', 'integer', 'min:2', 'max:100'],
            'status' => ['sometimes', 'string', 'in:pending,active,completed'],
        ]);

        $cohort->update($validated);

        return response()->json($cohort);
    }

    public function addMember(Request $request, string $id): JsonResponse
    {
        $cohort = Cohort::findOrFail($id);

        abort_unless($this->canAccessCohort($request, $cohort), 403);

        if ($cohort->members()->count() >= $cohort->max_members) {
            return response()->json(['message' => __('cohorts.max_members_reached')], 422);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

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
        abort_unless($this->canAccessCohort($request, $cohort), 403);

        $member = CohortMember::where('cohort_id', $id)
            ->where('id', $memberId)
            ->firstOrFail();

        $member->delete();

        return response()->json(['message' => __('cohorts.member_removed')]);
    }

    private function canAccessCohort(Request $request, Cohort $cohort): bool
    {
        $user = $request->user();

        return $user->isAdmin()
            || CohortMember::where('cohort_id', $cohort->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();
    }
}
