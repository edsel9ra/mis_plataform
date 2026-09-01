<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('relationship_id');
            $table->uuid('mentor_id');
            $table->uuid('mentee_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->string('status', 30)->default('assigned');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('relationship_id')->references('id')->on('mentorship_relationships')->cascadeOnDelete();
            $table->foreign('mentor_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('mentee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['mentor_id', 'mentee_id']);
            $table->index(['relationship_id', 'status']);
        });

        Schema::create('challenge_check_ins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('challenge_id')->nullable();
            $table->uuid('relationship_id');
            $table->uuid('mentee_id');
            $table->string('source', 30)->default('mentee_self_report');
            $table->unsignedSmallInteger('attention_score');
            $table->text('note')->nullable();
            $table->timestamp('checked_in_at');
            $table->timestamps();

            $table->foreign('challenge_id')->references('id')->on('challenges')->nullOnDelete();
            $table->foreign('relationship_id')->references('id')->on('mentorship_relationships')->cascadeOnDelete();
            $table->foreign('mentee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['relationship_id', 'source', 'checked_in_at']);
        });

        Schema::create('challenge_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('challenge_id');
            $table->uuid('relationship_id');
            $table->uuid('mentee_id');
            $table->text('note')->nullable();
            $table->string('status', 30)->default('submitted');
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('challenge_id')->references('id')->on('challenges')->cascadeOnDelete();
            $table->foreign('relationship_id')->references('id')->on('mentorship_relationships')->cascadeOnDelete();
            $table->foreign('mentee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['relationship_id', 'submitted_at']);
        });

        Schema::create('feedback_threads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('relationship_id');
            $table->uuid('challenge_id')->nullable();
            $table->uuid('mentor_id');
            $table->uuid('mentee_id');
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->foreign('relationship_id')->references('id')->on('mentorship_relationships')->cascadeOnDelete();
            $table->foreign('challenge_id')->references('id')->on('challenges')->nullOnDelete();
            $table->foreign('mentor_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('mentee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['relationship_id', 'challenge_id']);
        });

        Schema::create('feedback_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('feedback_thread_id');
            $table->uuid('author_id');
            $table->text('body')->nullable();
            $table->timestamps();

            $table->foreign('feedback_thread_id')->references('id')->on('feedback_threads')->cascadeOnDelete();
            $table->foreign('author_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['feedback_thread_id', 'created_at']);
        });

        Schema::create('emotional_check_ins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('relationship_id');
            $table->uuid('mentee_id');
            $table->unsignedSmallInteger('mood_score');
            $table->unsignedSmallInteger('stress_score')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('checked_in_at');
            $table->timestamps();

            $table->foreign('relationship_id')->references('id')->on('mentorship_relationships')->cascadeOnDelete();
            $table->foreign('mentee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['relationship_id', 'checked_in_at']);
        });

        Schema::create('neurocognitive_observations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('relationship_id');
            $table->uuid('mentor_id');
            $table->uuid('mentee_id');
            $table->uuid('challenge_id')->nullable();
            $table->unsignedSmallInteger('attention_score');
            $table->unsignedSmallInteger('engagement_score')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('observed_at');
            $table->timestamps();

            $table->foreign('relationship_id')->references('id')->on('mentorship_relationships')->cascadeOnDelete();
            $table->foreign('mentor_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('mentee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('challenge_id')->references('id')->on('challenges')->nullOnDelete();
            $table->index(['relationship_id', 'observed_at']);
        });

        Schema::create('neurocognitive_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('relationship_id');
            $table->uuid('mentor_id');
            $table->uuid('mentee_id');
            $table->string('type', 30);
            $table->string('severity', 20)->default('medium');
            $table->string('dedupe_key')->unique();
            $table->jsonb('data')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('relationship_id')->references('id')->on('mentorship_relationships')->cascadeOnDelete();
            $table->foreign('mentor_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('mentee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['mentor_id', 'resolved_at']);
        });

        Schema::create('systemic_maps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('relationship_id')->unique();
            $table->uuid('mentor_id');
            $table->uuid('mentee_id');
            $table->string('title', 200)->default('Mapa sistémico');
            $table->text('notes')->nullable();
            $table->boolean('is_private')->default(true);
            $table->timestamps();

            $table->foreign('relationship_id')->references('id')->on('mentorship_relationships')->cascadeOnDelete();
            $table->foreign('mentor_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('mentee_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('systemic_map_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('systemic_map_id');
            $table->string('node_key', 100);
            $table->string('label', 200);
            $table->string('kind', 50)->nullable();
            $table->jsonb('position')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('systemic_map_id')->references('id')->on('systemic_maps')->cascadeOnDelete();
            $table->unique(['systemic_map_id', 'node_key']);
        });

        Schema::create('systemic_map_edges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('systemic_map_id');
            $table->uuid('from_node_id');
            $table->uuid('to_node_id');
            $table->string('label', 200)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('systemic_map_id')->references('id')->on('systemic_maps')->cascadeOnDelete();
            $table->foreign('from_node_id')->references('id')->on('systemic_map_nodes')->cascadeOnDelete();
            $table->foreign('to_node_id')->references('id')->on('systemic_map_nodes')->cascadeOnDelete();
            $table->index('systemic_map_id');
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('relationship_id');
            $table->uuid('uploaded_by');
            $table->string('mediaable_type')->nullable();
            $table->uuid('mediaable_id')->nullable();
            $table->string('purpose', 40);
            $table->string('disk', 30)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('relationship_id')->references('id')->on('mentorship_relationships')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['mediaable_type', 'mediaable_id']);
            $table->index(['relationship_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('systemic_map_edges');
        Schema::dropIfExists('systemic_map_nodes');
        Schema::dropIfExists('systemic_maps');
        Schema::dropIfExists('neurocognitive_alerts');
        Schema::dropIfExists('neurocognitive_observations');
        Schema::dropIfExists('emotional_check_ins');
        Schema::dropIfExists('feedback_entries');
        Schema::dropIfExists('feedback_threads');
        Schema::dropIfExists('challenge_submissions');
        Schema::dropIfExists('challenge_check_ins');
        Schema::dropIfExists('challenges');
    }
};
