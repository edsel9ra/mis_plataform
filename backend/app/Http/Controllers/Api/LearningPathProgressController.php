<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\Progress\PathProgressService;
use App\Services\Progress\ProgressAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Learning-path progress metrics built from explicit challenge-module
 * links (RF-012.1-.6). Authorization reuses the progress panel's
 * ProgressAccessService exactly (self, assigned mentor + entitlement,
 * support, 404 for unassigned mentees).
 */
class LearningPathProgressController
{
    public function __construct(
        private PathProgressService $progress,
        private ProgressAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $subject = $request->user();
        $this->access->assertUserPanel($subject, $subject);

        return response()->json(['data' => $this->progress->forSubject($subject)]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->access->assertUserPanel($request->user(), $user);

        return response()->json(['data' => $this->progress->forSubject($user)]);
    }
}
