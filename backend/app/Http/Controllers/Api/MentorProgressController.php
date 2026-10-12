<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MenteeProgressFlagResource;
use App\Http\Resources\ProgressPanelResource;
use App\Models\User;
use App\Services\Progress\MentorProgressService;
use App\Services\Progress\ProgressAccessService;
use App\Services\Progress\ProgressPanelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * T-080 — mentor progress endpoints (RF-9).
 */
class MentorProgressController
{
    public function __construct(
        private MentorProgressService $portfolio,
        private ProgressPanelService $panels,
        private ProgressAccessService $access,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return MenteeProgressFlagResource::collection(
            $this->portfolio->portfolio($request->user()),
        );
    }

    public function show(Request $request, User $user): ProgressPanelResource
    {
        $viewer = $request->user();

        // 404 for a mentee the mentor is not assigned to (CA-9.3); 403 with a
        // reason when the mentee plan lacks the mentor workspace (CA-9.2).
        $access = $this->access->assertUserPanel($viewer, $user);

        $timezone = $user->timezone ?: (string) config('app.timezone', 'UTC');
        $panel = $this->panels->build($user, $viewer, null, $timezone);

        $panel['subject'] = ['id' => $user->id, 'name' => $user->full_name];
        $panel['viewer_role'] = $access['role'];
        $panel['collectives'] = [];

        return new ProgressPanelResource($panel);
    }
}
