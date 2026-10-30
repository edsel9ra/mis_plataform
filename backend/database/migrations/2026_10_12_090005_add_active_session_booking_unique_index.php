<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX_NAME = 'sessions_active_relationship_schedule_unique';

    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX ".self::INDEX_NAME." ON sessions (relationship_id, scheduled_at) WHERE status IN ('scheduled', 'in_progress') AND deleted_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX_NAME);
    }
};
