<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_schedule_changes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained()->restrictOnDelete();
            $table->timestamp('scheduled_at_before');
            $table->timestamp('scheduled_at_after');
            $table->string('timezone_before', 50);
            $table->string('timezone_after', 50);
            $table->unsignedSmallInteger('duration_minutes_before');
            $table->unsignedSmallInteger('duration_minutes_after');
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'occurred_at'], 'session_schedule_changes_session_occurred_index');
            $table->index(['actor_id', 'occurred_at'], 'session_schedule_changes_actor_occurred_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_schedule_changes');
    }
};
