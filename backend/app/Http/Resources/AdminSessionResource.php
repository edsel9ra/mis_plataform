<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge(
            collect($this->resource->attributesToArray())->only([
                'id', 'relationship_id', 'session_type', 'title', 'description', 'scheduled_at',
                'end_at', 'duration_minutes', 'status', 'timezone', 'meet_link', 'meet_event_id',
                'meet_attempts', 'meet_last_attempt_at', 'meet_last_error', 'meet_synced_schedule_hash',
                'recording_url', 'mentor_notes', 'mentee_notes', 'completed_at', 'completed_by',
                'canceled_at', 'canceled_by', 'canceled_late', 'cancellation_reason',
                'cancellation_lead_minutes', 'created_at', 'updated_at', 'deleted_at',
            ])->all(),
            $this->resource->relationsToArray(),
        );
    }
}
