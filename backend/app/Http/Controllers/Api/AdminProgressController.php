<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ProgressPanelResource;
use App\Models\User;
use App\Services\Progress\ProgressAccessService;
use App\Services\Progress\ProgressPanelService;
use Illuminate\Http\Request;

/**
 * T-082 — support read-only access (D11, DT-15).
 *
 * Guarded by the explicit `progress.view.support` permission, assigned to
 * super_admin/admin by seeder. Every view is audited by ProgressAccessService
 * (P5) without exposing personal content in the log.
 */
class AdminProgressController
{
    public function __construct(
        private ProgressPanelService $panels,
        private ProgressAccessService $access,
    ) {}

    public function show(Request $request): ProgressPanelResource
    {
        $viewer = $request->user();
        $subject = User::findOrFail((string) $request->input('user_id'));
        $access = $this->access->assertUserPanel($viewer, $subject);

        $timezone = $subject->timezone ?: (string) config('app.timezone', 'UTC');
        $panel = $this->panels->build($subject, $viewer, null, $timezone);

        $panel['subject'] = ['id' => $subject->id, 'name' => $subject->full_name];
        $panel['viewer_role'] = $access['role'];
        $panel['collectives'] = [];

        return new ProgressPanelResource($panel);
    }
}
