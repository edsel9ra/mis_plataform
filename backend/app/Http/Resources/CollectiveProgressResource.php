<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectiveProgressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $aggregate = (array) $this->resource['aggregate'];
        $suppressed = (bool) ($aggregate['suppressed'] ?? false);
        $subject = $this->resource['subject'] ?? null;

        return [
            'subject' => $subject,
            'viewer_role' => $this->resource['viewer_role'] ?? 'member',

            'state' => $aggregate['state'] ?? 'no_data',
            'suppressed' => $suppressed,
            'suppression_reason' => $suppressed ? ($aggregate['reason'] ?? 'insufficient_base') : null,

            // A suppressed aggregate never discloses metrics or the base size.
            'metrics' => $suppressed || ($aggregate['state'] ?? null) === 'no_data'
                ? null
                : [
                    'mean' => $aggregate['mean'] ?? null,
                    'sum' => $aggregate['sum'] ?? ['achieved' => 0, 'total' => 0],
                ],

            'base' => $suppressed
                ? null
                : [
                    'eligible_members' => $aggregate['eligible_members'] ?? null,
                    'contributing_members' => $aggregate['contributing_members'] ?? null,
                ],
        ];
    }
}
