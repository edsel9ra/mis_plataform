<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * T014.14 (plan §10): occurred_at/expires_at were the only timestamptz
     * columns in the database. Laravel writes the app-timezone wall clock
     * without offset while the session is UTC, so every stored instant
     * landed six hours early. Converting to timestamp without time zone
     * with `col AT TIME ZONE 'UTC'` unwraps the UTC mislabel and preserves
     * the stored wall clock byte for byte, session-independent. NOT NULL
     * and both indexes (expires_at; subject_id+occurred_at) are rebuilt by
     * Postgres with the new type.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE evidence_deletion_events
                ALTER COLUMN occurred_at TYPE timestamp(0) without time zone USING (occurred_at AT TIME ZONE 'UTC'),
                ALTER COLUMN expires_at TYPE timestamp(0) without time zone USING (expires_at AT TIME ZONE 'UTC')"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE evidence_deletion_events
                ALTER COLUMN occurred_at TYPE timestamp(0) with time zone USING (occurred_at AT TIME ZONE 'UTC'),
                ALTER COLUMN expires_at TYPE timestamp(0) with time zone USING (expires_at AT TIME ZONE 'UTC')"
        );
    }
};
