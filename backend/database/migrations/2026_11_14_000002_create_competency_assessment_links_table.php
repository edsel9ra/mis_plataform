<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competency_assessment_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('assessment_result_id')->nullable();
            $table->uuid('personality_assessment_id')->nullable();
            $table->uuid('skill_tag_id')->nullable();
            $table->string('ocean_factor', 1)->nullable();
            $table->timestamps();

            // The owning user's links follow the project convention and are
            // removed with the user.
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Non-destructive FKs: deleting a source result or a skill tag
            // that a link still references is blocked, so history is never
            // dropped silently by a cascade.
            $table->foreign('assessment_result_id')->references('id')->on('assessment_results')->restrictOnDelete();
            $table->foreign('personality_assessment_id')->references('id')->on('personality_assessments')->restrictOnDelete();
            $table->foreign('skill_tag_id')->references('id')->on('skill_tags')->restrictOnDelete();

            $table->index('user_id');
        });

        // Exactly one result source (catalog XOR personality) and exactly one
        // competency target (skill tag XOR OCEAN factor) per link.
        DB::statement("ALTER TABLE competency_assessment_links ADD CONSTRAINT cal_one_source CHECK (
            ((assessment_result_id IS NOT NULL)::int + (personality_assessment_id IS NOT NULL)::int) = 1
        )");
        DB::statement("ALTER TABLE competency_assessment_links ADD CONSTRAINT cal_one_competency CHECK (
            ((skill_tag_id IS NOT NULL)::int + (ocean_factor IS NOT NULL)::int) = 1
        )");
        DB::statement("ALTER TABLE competency_assessment_links ADD CONSTRAINT cal_ocean_factor CHECK (
            ocean_factor IS NULL OR ocean_factor IN ('O','C','E','A','N')
        )");

        // Duplicate prevention per (user, source, competency) combination.
        // Partial indexes keep each discriminated pair unique without
        // conflicting with the XOR checks.
        DB::statement('CREATE UNIQUE INDEX cal_unique_result_skill ON competency_assessment_links (user_id, assessment_result_id, skill_tag_id) WHERE assessment_result_id IS NOT NULL AND skill_tag_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX cal_unique_result_ocean ON competency_assessment_links (user_id, assessment_result_id, ocean_factor) WHERE assessment_result_id IS NOT NULL AND ocean_factor IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX cal_unique_personality_skill ON competency_assessment_links (user_id, personality_assessment_id, skill_tag_id) WHERE personality_assessment_id IS NOT NULL AND skill_tag_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX cal_unique_personality_ocean ON competency_assessment_links (user_id, personality_assessment_id, ocean_factor) WHERE personality_assessment_id IS NOT NULL AND ocean_factor IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_assessment_links');
    }
};
