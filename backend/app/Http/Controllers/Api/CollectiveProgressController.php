<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CollectiveProgressResource;
use App\Services\Progress\CollectiveProgressService;
use App\Services\Progress\EligibleParticipantsResolver;
use App\Services\Progress\ProgressAccessService;
use Illuminate\Http\Request;

/**
 * T-077 — collective aggregate endpoint (RF-10, RF-11).
 *
 * Managers and members receive exactly the same shape; only the `viewer_role`
 * differs, and the privacy threshold is uniform (RNF-2).
 */
class CollectiveProgressController
{
    public function __construct(
        private CollectiveProgressService $collectives,
        private EligibleParticipantsResolver $resolver,
        private ProgressAccessService $access,
    ) {}

    public function show(Request $request, string $type, string $id): CollectiveProgressResource
    {
        $viewer = $request->user();

        $access = $this->access->assertCollective($viewer, $type, $id);

        $subject = $this->resolver->nameFor($type, $id);
        abort_if($subject === null, 404);

        return new CollectiveProgressResource([
            'subject' => $subject,
            'viewer_role' => $access['role'],
            'aggregate' => $this->collectives->aggregate($type, $id),
        ]);
    }
}
