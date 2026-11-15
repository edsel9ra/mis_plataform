<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_deletion_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subject_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_type', 32);
            $table->string('competency_type', 32);
            $table->string('competency_key', 64);
            $table->string('change_type', 32);
            $table->uuid('actor_pseudonym')->unique();
            $table->timestampTz('occurred_at');
            $table->timestampTz('expires_at');
            $table->timestamps();

            $table->index('expires_at');
            $table->index(['subject_id', 'occurred_at']);
        });

        // Discriminated minimal event (política-retencion §4.1–§4.2): no source,
        // link or actor identifiers and no payload fields may be stored here.
        DB::statement("ALTER TABLE evidence_deletion_events ADD CONSTRAINT ede_source_type CHECK (
            source_type IN ('assessment_result','personality_assessment')
        )");
        DB::statement("ALTER TABLE evidence_deletion_events ADD CONSTRAINT ede_competency_type CHECK (
            competency_type IN ('skill_tag','ocean_factor')
        )");
        DB::statement("ALTER TABLE evidence_deletion_events ADD CONSTRAINT ede_change_type CHECK (
            change_type IN ('source_deleted')
        )");

        Schema::create('evidence_deletion_actor_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->unique()->constrained('evidence_deletion_events')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_deletion_actor_links');
        Schema::dropIfExists('evidence_deletion_events');
    }
};
