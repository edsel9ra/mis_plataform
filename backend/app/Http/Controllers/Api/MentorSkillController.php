<?php

namespace App\Http\Controllers\Api;

use App\Actions\SyncMentorSkillsAction;
use App\Http\Requests\Mentor\SyncMentorSkillsRequest;
use App\Http\Resources\AdminMentorResource;
use App\Http\Resources\UserProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class MentorSkillController
{
    public function updateOwn(SyncMentorSkillsRequest $request, SyncMentorSkillsAction $action): JsonResponse
    {
        $mentor = $action->execute($request->user(), $request->validated()['skills']);

        return response()->json(new UserProfileResource($mentor));
    }

    public function updateForMentor(
        SyncMentorSkillsRequest $request,
        SyncMentorSkillsAction $action,
        string $id,
    ): JsonResponse {
        $mentor = User::byRole('mentor')->findOrFail($id);
        $mentor = $action->execute($mentor, $request->validated()['skills']);

        return response()->json(new AdminMentorResource($mentor));
    }
}
