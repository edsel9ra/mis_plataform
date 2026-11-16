<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * T015.6 (plan §4 step 4): covering indexes for the history feeds. The
     * partial expression indexes must match the WHERE clauses emitted by
     * ModuleCompletionHistoryAdapter and CompetencyHistoryAdapter
     * textually, so the planner can apply them.
     */
    public function up(): void
    {
        DB::statement(
            "CREATE INDEX activity_log_module_completion_user_idx
                ON activity_log (((properties::jsonb ->> 'user_id')), event)
                WHERE log_name = 'module_completion'"
        );
        DB::statement(
            "CREATE INDEX activity_log_progress_subject_idx
                ON activity_log (((properties::jsonb ->> 'subject_id')), event)
                WHERE log_name = 'progress'"
        );
        DB::statement('CREATE INDEX challenges_mentee_id_index ON challenges (mentee_id)');
        DB::statement(
            'CREATE INDEX challenge_submissions_mentee_challenge_index
                ON challenge_submissions (mentee_id, challenge_id)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS activity_log_module_completion_user_idx');
        DB::statement('DROP INDEX IF EXISTS activity_log_progress_subject_idx');
        DB::statement('DROP INDEX IF EXISTS challenges_mentee_id_index');
        DB::statement('DROP INDEX IF EXISTS challenge_submissions_mentee_challenge_index');
    }
};
