<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Audited correspondence resolution (política-retención §4.4–§4.5): the
 * event id and, when the actor still exists, only its id and name. The
 * pseudonym, subject, event content and any further user field are never
 * exposed.
 */
class EvidenceDeletionActorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{event_id: string, actor: \App\Models\User|null} $resource */
        $resource = $this->resource;
        $actor = $resource['actor'];

        return [
            'event_id' => $resource['event_id'],
            'actor' => $actor === null ? null : [
                'id' => $actor->id,
                'name' => $actor->name,
            ],
        ];
    }
}
