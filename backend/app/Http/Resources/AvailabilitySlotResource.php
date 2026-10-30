<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailabilitySlotResource extends JsonResource
{
    /** @return array{start: string, end: string, timezone: string} */
    public function toArray(Request $request): array
    {
        return [
            'start' => $this->resource['start']->format(DATE_ATOM),
            'end' => $this->resource['end']->format(DATE_ATOM),
            'timezone' => $this->resource['timezone'],
        ];
    }
}
