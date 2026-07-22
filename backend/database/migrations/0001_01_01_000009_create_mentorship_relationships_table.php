<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentorship_relationships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 20); // personal, familiar, grupal, corporate
            $table->uuidMorphs('source'); // user, family_group, cohort, company
            $table->uuid('mentor_id');
            $table->decimal('match_score', 5, 2)->nullable(); // 0-100
            $table->string('status', 20)->default('pending'); // matched, pending, active, paused, completed, canceled
            $table->text('objectives')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('mentor_id')->references('id')->on('users');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentorship_relationships');
    }
};
