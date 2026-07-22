<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_paths', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->jsonb('title'); // multi-lang
            $table->jsonb('description')->nullable();
            $table->jsonb('personality_tags')->nullable(); // qué perfiles OCEAN son ideales
            $table->string('client_type', 20)->nullable(); // personal, familiar, grupal, empresa
            $table->string('level', 20)->default('beginner'); // beginner, intermediate, advanced
            $table->integer('estimated_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('path_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learning_path_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->integer('estimated_minutes')->nullable();
            $table->timestamps();

            $table->foreign('learning_path_id')->references('id')->on('learning_paths')->cascadeOnDelete();
        });

        Schema::create('learning_resources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('module_id')->nullable();
            $table->string('type', 30); // video, document, link, quiz, exercise
            $table->jsonb('title'); // multi-lang
            $table->jsonb('description')->nullable();
            $table->string('url');
            $table->integer('order')->default(0);
            $table->boolean('is_free')->default(false);
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('path_modules')->cascadeOnDelete();
        });

        Schema::create('user_learning_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('learning_path_id');
            $table->uuid('current_module_id')->nullable();
            $table->decimal('progress', 5, 2)->default(0); // 0-100
            $table->string('status', 20)->default('not_started'); // not_started, in_progress, completed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('learning_path_id')->references('id')->on('learning_paths')->cascadeOnDelete();
            $table->unique(['user_id', 'learning_path_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_learning_progress');
        Schema::dropIfExists('learning_resources');
        Schema::dropIfExists('path_modules');
        Schema::dropIfExists('learning_paths');
    }
};
