<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objectives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scope', 20);                 // individual | collective
            $table->uuidMorphs('owner');                 // user | family_group | cohort | company
            $table->uuid('relationship_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->text('success_criteria');            // criterio de logro obligatorio
            $table->string('status', 20)->default('not_started');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('achieved_at')->nullable();
            $table->uuid('proposed_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('relationship_id')->references('id')->on('mentorship_relationships')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('proposed_by')->references('id')->on('users')->nullOnDelete();

            $table->index('status');
            $table->index('due_at');
            $table->index('relationship_id');
        });

        // P18: integrity is enforced by the database, not only by the application.
        DB::statement(<<<'SQL'
            ALTER TABLE objectives
            ADD CONSTRAINT objectives_scope_check
            CHECK (scope IN ('individual', 'collective'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE objectives
            ADD CONSTRAINT objectives_status_check
            CHECK (status IN ('not_started', 'in_progress', 'achieved', 'abandoned'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE objectives
            ADD CONSTRAINT objectives_owner_scope_check
            CHECK (
                (scope = 'individual' AND owner_type = 'user')
                OR (scope = 'collective' AND owner_type IN ('family_group', 'cohort', 'company'))
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE objectives
            ADD CONSTRAINT objectives_success_criteria_not_blank
            CHECK (length(btrim(success_criteria)) > 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('objectives');
    }
};
