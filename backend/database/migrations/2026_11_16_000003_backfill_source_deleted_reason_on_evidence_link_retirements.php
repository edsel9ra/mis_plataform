<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * T015.10 — evidence-link retirements recorded before T015.4 carry no
     * `reason` marker, so the competency history feed would expose the real
     * deleter. A retirement provably caused by a source deletion is marked
     * `reason=source_deleted` (+ `reason_backfilled` so down() can undo only
     * what this migration added). The match is exact:
     * RetireEvidenceLinksForSourceAction passes the same $now to the
     * evidence_deletion_events row and to the retirement activity, so the
     * deletion instant and the retirement instant are identical.
     */
    public function up(): void
    {
        DB::update(
            "UPDATE activity_log a
            SET properties = (a.properties::jsonb || '{\"reason\":\"source_deleted\",\"reason_backfilled\":true}'::jsonb)::json
            WHERE a.log_name = 'progress'
              AND a.event = 'competency.evidence_link_changed'
              AND (a.properties::jsonb -> 'after') = 'null'::jsonb
              AND (a.properties::jsonb ->> 'reason') IS NULL
              AND EXISTS (
                SELECT 1 FROM evidence_deletion_events e
                WHERE e.subject_id::text = a.properties::jsonb ->> 'subject_id'
                  AND e.source_type = a.properties::jsonb -> 'before' ->> 'source_type'
                  AND e.competency_type = a.properties::jsonb -> 'before' ->> 'competency_type'
                  AND e.competency_key = a.properties::jsonb -> 'before' ->> 'competency_key'
                  AND (e.occurred_at AT TIME ZONE ?) = (a.properties::jsonb ->> 'occurred_at')::timestamptz
              )",
            [config('app.timezone')],
        );
    }

    public function down(): void
    {
        DB::update(
            "UPDATE activity_log
            SET properties = (properties::jsonb - 'reason' - 'reason_backfilled')::json
            WHERE log_name = 'progress'
              AND event = 'competency.evidence_link_changed'
              AND (properties::jsonb ->> 'reason_backfilled') = 'true'",
        );
    }
};
