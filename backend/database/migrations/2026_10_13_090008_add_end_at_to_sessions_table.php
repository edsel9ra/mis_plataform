<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->timestamp('end_at')->nullable();
            $table->index('end_at', 'sessions_end_at_index');
            $table->index(['status', 'end_at'], 'sessions_status_end_at_index');
        });

        DB::statement(<<<'SQL'
            UPDATE sessions
            SET end_at = scheduled_at + duration_minutes * INTERVAL '1 minute'
            WHERE end_at IS NULL
            SQL);

        DB::statement('ALTER TABLE sessions ALTER COLUMN end_at SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropIndex('sessions_status_end_at_index');
            $table->dropIndex('sessions_end_at_index');
            $table->dropColumn('end_at');
        });
    }
};
