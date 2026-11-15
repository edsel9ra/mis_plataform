<?php

namespace App\Http\Controllers\Api;

use App\Actions\IdentifyEvidenceDeletionActorAction;
use App\Http\Resources\EvidenceDeletionActorResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Audit endpoint (spec 014 — política de retención §4.4): the only surface
 * that resolves the real actor behind a minimal deletion event. Guarded by
 * role:super_admin|admin plus the dedicated audit.identify_actor permission;
 * every resolution is audited by the action.
 */
class EvidenceDeletionAuditController
{
    public function __construct(
        private IdentifyEvidenceDeletionActorAction $identify,
    ) {}

    public function actor(Request $request, string $event): EvidenceDeletionActorResource
    {
        $actor = $this->identify->execute($event, $request->user(), CarbonImmutable::now());

        return new EvidenceDeletionActorResource([
            'event_id' => $event,
            'actor' => $actor,
        ]);
    }
}
