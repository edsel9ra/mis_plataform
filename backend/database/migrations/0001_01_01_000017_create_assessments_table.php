<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('module_id')->nullable();
            $table->jsonb('title');
            $table->jsonb('description')->nullable();
            $table->jsonb('questions');
            $table->integer('passing_score')->default(70);
            $table->integer('time_limit_minutes')->nullable();
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('path_modules')->nullOnDelete();
        });

        Schema::create('assessment_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('assessment_id');
            $table->jsonb('answers');
            $table->decimal('score', 5, 2);
            $table->boolean('passed')->default(false);
            $table->integer('time_spent_seconds')->nullable();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assessment_id')->references('id')->on('assessments')->cascadeOnDelete();
            $table->unique(['user_id', 'assessment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_results');
        Schema::dropIfExists('assessments');
    }
};
