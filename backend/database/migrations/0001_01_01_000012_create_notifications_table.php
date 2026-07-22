<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type', 50); // session_reminder, new_message, match_suggestion, status_change, etc
            $table->jsonb('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
