<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateRelationships = DB::table('feedback_threads')
            ->select('relationship_id')
            ->whereNull('challenge_id')
            ->groupBy('relationship_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('relationship_id');

        foreach ($duplicateRelationships as $relationshipId) {
            $threadIds = DB::table('feedback_threads')
                ->where('relationship_id', $relationshipId)
                ->whereNull('challenge_id')
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id');
            $keeperId = $threadIds->shift();

            if (!$keeperId) {
                continue;
            }

            DB::table('feedback_entries')
                ->whereIn('feedback_thread_id', $threadIds->all())
                ->update(['feedback_thread_id' => $keeperId]);
            DB::table('feedback_threads')
                ->whereIn('id', $threadIds->all())
                ->delete();
        }

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX feedback_threads_one_general_per_relationship
            ON feedback_threads (relationship_id)
            WHERE challenge_id IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS feedback_threads_one_general_per_relationship');
    }
};
