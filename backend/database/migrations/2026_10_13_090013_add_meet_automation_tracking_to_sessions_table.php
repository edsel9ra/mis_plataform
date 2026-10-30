<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->unsignedInteger('meet_attempts')->default(0);
            $table->timestamp('meet_last_attempt_at')->nullable();
            $table->string('meet_last_error', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropColumn(['meet_attempts', 'meet_last_attempt_at', 'meet_last_error']);
        });
    }
};
