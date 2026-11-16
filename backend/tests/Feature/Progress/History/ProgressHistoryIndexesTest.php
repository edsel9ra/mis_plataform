<?php

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\LearningPath;
use App\Models\MentorshipRelationship;
use App\Models\PathModule;
use App\Models\User;
use App\Models\UserLearningProgress;
use App\Services\Progress\History\ChallengeActivityHistoryAdapter;
use App\Services\Progress\History\ChallengeSubmissionHistoryAdapter;
use App\Services\Progress\History\CompetencyHistoryAdapter;
use App\Services\Progress\History\ModuleCompletionHistoryAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * T015.6 — covering indexes for the history feeds (plan §4 step 4). Each
 * index must exist with its partial predicate and must be picked by the
 * planner for the exact SQL the adapters emit.
 */
uses(RefreshDatabase::class)->group('progress');

const HIST_IDX_MIGRATION = 'database/migrations/2026_11_16_000002_add_progress_history_indexes.php';

beforeEach(function (): void {
    Role::findOrCreate('mentor');
    Role::findOrCreate('mentee');

    $this->mentor = User::factory()->mentor()->create(['client_type' => 'personal']);
    $this->mentor->assignRole('mentor');
    $this->subject = User::factory()->create(['client_type' => 'personal']);
    $this->subject->assignRole('mentee');
    $this->relationship = MentorshipRelationship::create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $this->subject->id,
        'mentor_id' => $this->mentor->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
});

function histIdxIndexes(): \Illuminate\Support\Collection
{
    return DB::table('pg_indexes')
        ->select('indexname', 'indexdef')
        ->where('schemaname', 'public')
        ->get()
        ->keyBy('indexname');
}

/** EXPLAIN the first SQL statement an adapter emits, seqscan disabled. */
function histIdxPlan(callable $run): string
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $run();
    $logged = DB::getQueryLog();
    DB::disableQueryLog();

    $query = $logged[0];

    DB::beginTransaction();
    DB::statement('SET LOCAL enable_seqscan = off');
    $plan = collect(DB::select('EXPLAIN '.$query['query'], $query['bindings']))
        ->pluck('QUERY PLAN')
        ->implode("\n");
    DB::rollBack();

    return $plan;
}

it('creates the four history indexes with their partial predicates', function (): void {
    $indexes = histIdxIndexes();

    foreach ([
        'activity_log_module_completion_user_idx',
        'activity_log_progress_subject_idx',
        'challenges_mentee_id_index',
        'challenge_submissions_mentee_challenge_index',
    ] as $name) {
        expect($indexes->has($name))->toBeTrue("missing index {$name}");
    }

    expect($indexes['activity_log_module_completion_user_idx']->indexdef)
        ->toContain("'module_completion'::text")
        ->toContain("->> 'user_id'");
    expect($indexes['activity_log_progress_subject_idx']->indexdef)
        ->toContain("'progress'::text")
        ->toContain("->> 'subject_id'");
});

it('uses the module-completion subject index for the completion feed', function (): void {
    $plan = histIdxPlan(
        fn () => (new ModuleCompletionHistoryAdapter)->dated($this->subject, null, 10),
    );

    expect($plan)->toContain('activity_log_module_completion_user_idx');
});

it('uses the progress subject index for the competency feed', function (): void {
    $plan = histIdxPlan(
        fn () => (new CompetencyHistoryAdapter)->dated($this->subject, null, 10),
    );

    expect($plan)->toContain('activity_log_progress_subject_idx');
});

it('uses the mentee indexes for the challenge feeds', function (): void {
    $plan = histIdxPlan(
        fn () => (new ChallengeActivityHistoryAdapter)->dated($this->subject, null, 10),
    );
    expect($plan)->toContain('challenges_mentee_id_index');

    $plan = histIdxPlan(
        fn () => (new ChallengeSubmissionHistoryAdapter)->dated($this->subject, null, 10),
    );
    expect($plan)->toContain('challenge_submissions_mentee_challenge_index')
        ->and($plan)->toContain('challenges_mentee_id_index');
});

it('reverses and reapplies cleanly', function (): void {
    Artisan::call('migrate:rollback', ['--path' => HIST_IDX_MIGRATION]);
    expect(histIdxIndexes()->has('activity_log_module_completion_user_idx'))->toBeFalse()
        ->and(histIdxIndexes()->has('activity_log_progress_subject_idx'))->toBeFalse()
        ->and(histIdxIndexes()->has('challenges_mentee_id_index'))->toBeFalse()
        ->and(histIdxIndexes()->has('challenge_submissions_mentee_challenge_index'))->toBeFalse();

    Artisan::call('migrate', ['--path' => HIST_IDX_MIGRATION]);
    expect(histIdxIndexes()->has('activity_log_module_completion_user_idx'))->toBeTrue()
        ->and(histIdxIndexes()->has('activity_log_progress_subject_idx'))->toBeTrue()
        ->and(histIdxIndexes()->has('challenges_mentee_id_index'))->toBeTrue()
        ->and(histIdxIndexes()->has('challenge_submissions_mentee_challenge_index'))->toBeTrue();
});
