<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\LearningMapResource;
use App\Models\User;
use App\Services\Progress\PathProgressService;
use App\Services\Progress\ProgressAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Individual learning map (RF-013.1–.4, .9). Authorization reuses the
 * progress panel's ProgressAccessService exactly — same matrix as the
 * learning-paths endpoints: self, assigned mentor + entitlement, support,
 * 404 for unassigned mentees (no new permissions, RF-013.2).
 */
class LearningMapController
{
    public function __construct(
        private PathProgressService $progress,
        private ProgressAccessService $access,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $subject = $request->user();
        $this->access->assertUserPanel($subject, $subject);

        return LearningMapResource::collection($this->progress->mapForSubject($subject));
    }

    public function show(Request $request, User $user): AnonymousResourceCollection
    {
        $this->access->assertUserPanel($request->user(), $user);

        return LearningMapResource::collection($this->progress->mapForSubject($user));
    }
}
