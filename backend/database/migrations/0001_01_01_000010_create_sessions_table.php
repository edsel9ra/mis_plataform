<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('relationship_id');
            $table->string('session_type', 20)->default('individual'); // individual, family, group, corporate
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->timestamp('scheduled_at');
            $table->integer('duration_minutes')->default(60);
            $table->string('meet_link')->nullable();
            $table->string('meet_event_id')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->text('mentor_notes')->nullable();
            $table->text('mentee_notes')->nullable();
            $table->string('recording_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('relationship_id')->references('id')->on('mentorship_relationships')->cascadeOnDelete();
            $table->index('scheduled_at');
            $table->index('status');
        });

        Schema::create('session_attendees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('session_id');
            $table->uuid('user_id');
            $table->boolean('attended')->default(false);
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_attendees');
        Schema::dropIfExists('sessions');
    }
};
