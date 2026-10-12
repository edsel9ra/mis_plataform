<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->string('category', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('user_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('skill_tag_id');
            $table->string('level', 20)->default('intermediate'); // beginner, intermediate, advanced, expert
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('skill_tag_id')->references('id')->on('skill_tags')->cascadeOnDelete();
            $table->unique(['user_id', 'skill_tag_id']);
        });

        Schema::create('match_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id'); // mentee
            $table->uuid('mentor_id');
            $table->string('context_type', 30)->default('personal'); // personal, familiar, grupal, empresa
            $table->uuid('context_id')->nullable();
            $table->decimal('score', 5, 2); // 0-100
            $table->jsonb('breakdown')->nullable(); // desglose del score
            $table->jsonb('personality_compatibility')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('mentor_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'mentor_id']);
            $table->index('score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_scores');
        Schema::dropIfExists('user_skills');
        Schema::dropIfExists('skill_tags');
    }
};
