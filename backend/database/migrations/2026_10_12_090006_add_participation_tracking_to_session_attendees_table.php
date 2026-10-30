<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_attendees', function (Blueprint $table): void {
            $table->timestamp('left_at')->nullable();
        });

        Schema::create('session_participant_exclusions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('excluded_at');
            $table->timestamps();
            $table->unique(['session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_participant_exclusions');

        Schema::table('session_attendees', function (Blueprint $table): void {
            $table->dropColumn('left_at');
        });
    }
};
