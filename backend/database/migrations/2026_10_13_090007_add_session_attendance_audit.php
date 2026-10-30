<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_attendees', function (Blueprint $table): void {
            $table->string('attendance_status', 20)->default('not_confirmed');
            $table->foreignUuid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
        });

        DB::table('session_attendees')
            ->where('attended', true)
            ->update(['attendance_status' => 'attended']);

        Schema::create('session_attendance_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_attendee_id')
                ->constrained('session_attendees')
                ->restrictOnDelete();
            $table->string('event_type', 20);
            $table->string('attendance_status', 20);
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['session_attendee_id', 'occurred_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE session_attendance_events
            ADD CONSTRAINT session_attendance_events_contract_check CHECK (
                (event_type = 'initialization'
                    AND attendance_status = 'not_confirmed'
                    AND recorded_by IS NULL)
                OR
                (event_type = 'confirmation'
                    AND attendance_status IN ('attended', 'absent')
                    AND recorded_by IS NOT NULL)
            )
            SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX session_attendance_events_initialization_unique
            ON session_attendance_events (session_attendee_id)
            WHERE event_type = 'initialization'
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('session_attendance_events');

        Schema::table('session_attendees', function (Blueprint $table): void {
            $table->dropForeign(['confirmed_by']);
            $table->dropColumn(['attendance_status', 'confirmed_by', 'confirmed_at']);
        });
    }
};
