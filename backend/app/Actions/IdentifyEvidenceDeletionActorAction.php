<?php

namespace App\Actions;

use App\Models\EvidenceDeletionActorLink;
use App\Models\EvidenceDeletionEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Resolve the real actor behind a minimal deletion event
 * (docs/politica-retencion.md §4.3–§4.5, plan §9). Reachable only behind
 * the dedicated audit.identify_actor permission. Expired-but-not-yet-purged
 * events are reported as missing — the correspondence must not outlive the
 * retention window. Every lookup is audited on the 'audit' channel with the
 * minimum needed (event id, auditor id, instant): the identified identity,
 * the pseudonym, the subject and the event content are never logged, so the
 * audit itself does not reproduce the correspondence (§4.5).
 */
class IdentifyEvidenceDeletionActorAction
{
    public function execute(string $eventId, User $auditor, CarbonImmutable $now): ?User
    {
        $event = EvidenceDeletionEvent::query()
            ->whereKey($eventId)
            ->where('expires_at', '>', $now)
            ->first();

        if ($event === null) {
            throw (new ModelNotFoundException)->setModel(EvidenceDeletionEvent::class, [$eventId]);
        }

        $link = EvidenceDeletionActorLink::query()
            ->where('event_id', $event->id)
            ->first();

        // withTrashed: a soft-deleted actor stays identifiable; a hard-deleted
        // one left actor_id null (FK nullOnDelete) and resolves to null.
        $actor = $link?->actor_id !== null
            ? User::withTrashed()->find($link->actor_id)
            : null;

        activity('audit')
            ->causedBy($auditor)
            ->event('audit.evidence_deletion_actor_identified')
            ->withProperties([
                'event_id' => $event->id,
                'auditor_id' => $auditor->id,
                'occurred_at' => $now->toIso8601String(),
            ])
            ->log('audit.evidence_deletion_actor_identified');

        return $actor;
    }
}
