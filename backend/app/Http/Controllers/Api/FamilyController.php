<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Family\AddFamilyMemberRequest;
use App\Http\Requests\Family\StoreFamilyGroupRequest;
use App\Http\Requests\Family\UpdateFamilyGroupRequest;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FamilyController
{
    public function store(StoreFamilyGroupRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $group = DB::transaction(function () use ($request, $validated) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $user->update(['client_type' => 'familiar']);

            $group = FamilyGroup::create([
                'head_user_id' => $user->id,
                'family_name' => $validated['family_name'],
                'description' => $validated['description'] ?? null,
            ]);

            FamilyMember::create([
                'family_group_id' => $group->id,
                'user_id' => $user->id,
                'full_name' => $user->full_name,
                'relationship' => 'head',
            ]);

            return $group->load('members');
        });

        return response()->json($group, 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $group = FamilyGroup::with(['head', 'members.user.personalityAssessment'])
            ->findOrFail($id);

        abort_unless($this->canAccessFamily($request, $group), 403);

        return response()->json($group);
    }

    public function update(UpdateFamilyGroupRequest $request, string $id): JsonResponse
    {
        $group = FamilyGroup::findOrFail($id);

        abort_unless($this->canManageFamily($request, $group), 403);

        $validated = $request->validated();

        $group->update($validated);

        return response()->json($group);
    }

    public function addMember(AddFamilyMemberRequest $request, string $id): JsonResponse
    {
        $group = FamilyGroup::findOrFail($id);

        abort_unless($this->canManageFamily($request, $group), 403);

        $validated = $request->validated();

        $userId = null;
        if (!empty($validated['email'])) {
            $user = \App\Models\User::where('email', $validated['email'])->first();
            if ($user) {
                $userId = $user->id;
            }
        }

        $member = FamilyMember::create([
            'family_group_id' => $group->id,
            'user_id' => $userId,
            'full_name' => $validated['full_name'],
            'age' => $validated['age'] ?? null,
            'relationship' => $validated['relationship'] ?? null,
        ]);

        return response()->json($member, 201);
    }

    public function removeMember(Request $request, string $id, string $memberId): JsonResponse
    {
        $group = FamilyGroup::findOrFail($id);
        abort_unless($this->canManageFamily($request, $group), 403);

        $member = FamilyMember::where('family_group_id', $id)
            ->where('id', $memberId)
            ->firstOrFail();

        $member->delete();

        return response()->json(['message' => __('family.member_removed')]);
    }

    private function canAccessFamily(Request $request, FamilyGroup $group): bool
    {
        $user = $request->user();

        return $user->isAdmin()
            || $group->head_user_id === $user->id
            || FamilyMember::where('family_group_id', $group->id)->where('user_id', $user->id)->exists();
    }

    private function canManageFamily(Request $request, FamilyGroup $group): bool
    {
        $user = $request->user();

        return $user->isAdmin() || $group->head_user_id === $user->id;
    }
}
