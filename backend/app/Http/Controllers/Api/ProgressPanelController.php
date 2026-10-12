<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ProgressPanelResource;
use App\Models\User;
use App\Services\Progress\CollectiveProgressService;
use App\Services\Progress\ProgressAccessService;
use App\Services\Progress\ProgressPanelService;
use Illuminate\Http\Request;

/**
 * T-072 — the individual progress panel (RF-3..RF-8, RF-11).
 */
class ProgressPanelController
{
    public function __construct(
        private ProgressPanelService $panels,
        private CollectiveProgressService $collectives,
        private ProgressAccessService $access,
    ) {}

    public function show(Request $request): ProgressPanelResource
    {
        $viewer = $request->user();
        $subjectId = (string) $request->input('subject_id', $viewer->id);
        $subject = $subjectId === $viewer->id ? $viewer : User::findOrFail($subjectId);

        $access = $this->access->assertUserPanel($viewer, $subject);

        $timezone = $subject->timezone ?: (string) config('app.timezone', 'UTC');
        $panel = $this->panels->build($subject, $viewer, null, $timezone);

        $panel['subject'] = [
            'id' => $subject->id,
            'name' => $subject->full_name,
        ];
        $panel['viewer_role'] = $access['role'];

        // A collective aggregate is only disclosed to the member himself
        // (RF-11, CA-11.1). Mentors and support do not see it here.
        $panel['collectives'] = $access['role'] === 'self'
            ? $this->collectives->forUser($subject)
            : [];

        return new ProgressPanelResource($panel);
    }
}
