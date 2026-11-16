<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Unified history page (RF-015.1/.7/.8): the exact contract
 * {data, undated, next_cursor}. Each event exposes only the nine contract
 * keys; occurred_at is serialized as ISO-8601 UTC and the frontend groups
 * by day in the viewer's zone.
 */
class ProgressHistoryResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'data' => array_map(self::event(...), $this->resource['data']),
            'undated' => array_map(self::event(...), $this->resource['undated']),
            'next_cursor' => $this->resource['next_cursor'],
        ];
    }

    private static function event(array $event): array
    {
        return [
            'id' => $event['id'],
            'type' => $event['type'],
            'domain' => $event['domain'],
            'occurred_at' => $event['occurred_at']?->utc()->toIso8601String(),
            'actor' => $event['actor'],
            'subject_ref' => $event['subject_ref'],
            'before' => $event['before'],
            'after' => $event['after'],
            'incomplete' => $event['incomplete'],
        ];
    }
}
