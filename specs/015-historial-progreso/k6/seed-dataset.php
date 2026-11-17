<?php

/**
 * Spec 015 benchmark dataset (benchmark.md §Dataset de referencia).
 *
 * One-off script, run ONLY against a dedicated *_bench database with the
 * dev backend image (factories need dev dependencies):
 *
 *   docker compose run --rm --no-deps \
 *     -v "$PWD/specs/015-historial-progreso/k6:/bench:ro" -v /tmp/mis-bench/out:/bench-out \
 *     -e DB_DATABASE=mis_project_bench -e RUN_DATABASE_BOOTSTRAP=false \
 *     -e REDIS_DB=5 -e REDIS_CACHE_DB=6 -e REDIS_QUEUE_DB=7 -e REDIS_PREFIX=mis_bench_ \
 *     -e CACHE_PREFIX=mis_bench_ -e BROADCAST_CONNECTION=log \
 *     backend php /bench/seed-dataset.php
 *
 * Subject data is written through the real Actions / audited models so the
 * activity_log rows have exactly the production shape; the noise is bulk
 * SQL with the same log_name/event/properties shape for other users.
 * Writes /bench-out/fixtures.json (bearer tokens + ids) for the k6 script —
 * throwaway tokens for a throwaway database; delete the file after the run.
 */

use App\Actions\RetireEvidenceLinksForSourceAction;
use App\Actions\SetChallengePathModuleAction;
use App\Actions\SetCompetencyAssessmentLinkAction;
use App\Actions\TransitionChallengeStatusAction;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\CompetencyAssessmentLink;
use App\Models\LearningPath;
use App\Models\MentorshipRelationship;
use App\Models\Objective;
use App\Models\ObjectiveEvidence;
use App\Models\PathModule;
use App\Models\PersonalityAssessment;
use App\Models\Plan;
use App\Models\SkillTag;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserLearningProgress;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require '/var/www/backend/vendor/autoload.php';
$app = require '/var/www/backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const BENCH_SUBJECTS = 50;
const BENCH_SUBJECTS_PER_MENTOR = 5;
const BENCH_NOISE_USERS = 2000;
const BENCH_NOISE_ROWS = 200000;
const BENCH_OUT = '/bench-out/fixtures.json';

$database = (string) DB::connection()->getDatabaseName();
if (app()->environment('production') || ! str_ends_with($database, '_bench')) {
    fwrite(STDERR, "Refusing to seed: database '{$database}' is not a *_bench database.\n");
    exit(1);
}
if (DB::table('users')->exists() || DB::table('activity_log')->exists()) {
    fwrite(STDERR, "Refusing to seed: '{$database}' already has users or activity_log rows. Recreate it first.\n");
    exit(1);
}

$tz = (string) config('app.timezone');
$realNow = CarbonImmutable::now();
$started = microtime(true);

/**
 * Bulk noise in activity_log: rows [$from, $to) spread over 2 000 other
 * users, a quarter per feed shape (objective, challenge, module_completion,
 * progress), timestamps uniformly over the last 365 days (local wall clock,
 * like every Laravel-written created_at in this project).
 */
function benchInsertNoise(int $from, int $to, string $tz): void
{
    $objectiveMorph = (new Objective)->getMorphClass();
    $challengeMorph = (new Challenge)->getMorphClass();
    $moduleMorph = (new PathModule)->getMorphClass();
    $linkMorph = (new CompetencyAssessmentLink)->getMorphClass();
    $userMorph = (new User)->getMorphClass();

    DB::statement(sprintf(<<<'SQL'
        WITH noise AS (
            SELECT g,
                   g %% 4 AS kind,
                   md5('bench-noise-user-' || (g %% %d))::uuid AS uid,
                   md5('bench-noise-subject-' || g)::uuid AS sid,
                   md5('bench-noise-extra-' || g)::uuid AS xid,
                   ((now() AT TIME ZONE :tz1) - random() * interval '365 days')::timestamp(0) AS at_local
            FROM generate_series(%d, %d) AS g
        )
        INSERT INTO activity_log
            (log_name, description, subject_type, subject_id, causer_type, causer_id, properties, event, created_at, updated_at)
        SELECT
            CASE kind WHEN 0 THEN 'objective' WHEN 1 THEN 'challenge' WHEN 2 THEN 'module_completion' ELSE 'progress' END,
            ev,
            CASE kind WHEN 0 THEN :objective WHEN 1 THEN :challenge WHEN 2 THEN :module ELSE :link END,
            sid,
            CASE kind WHEN 2 THEN NULL ELSE :user END,
            CASE kind WHEN 2 THEN NULL ELSE uid END,
            CASE kind
                WHEN 0 THEN CASE WHEN ev = 'created'
                    THEN json_build_object('attributes', json_build_object('status', 'not_started'))
                    ELSE json_build_object('attributes', json_build_object('status', 'in_progress'),
                                           'old', json_build_object('status', 'not_started')) END
                WHEN 1 THEN json_build_object(
                    'challenge_id', sid, 'actor', uid,
                    'before', json_build_object('status', 'assigned'),
                    'after', json_build_object('status', 'in_progress'),
                    'occurred_at', at_local AT TIME ZONE :tz2)
                WHEN 2 THEN json_build_object(
                    'actor', 'system', 'user_id', uid, 'path_module_id', sid, 'result', 'completed',
                    'triggered_by', json_build_object('actor_id', uid, 'event', 'challenge.status_transition', 'challenge_id', xid),
                    'occurred_at', at_local AT TIME ZONE :tz3)
                ELSE json_build_object(
                    'subject_id', uid, 'link_id', sid, 'actor_id', uid, 'before', NULL,
                    'after', json_build_object('source_type', 'assessment_result', 'source_id', xid,
                                               'competency_type', 'skill_tag', 'competency_key', xid),
                    'occurred_at', at_local AT TIME ZONE :tz4)
            END,
            ev,
            at_local,
            at_local
        FROM (
            SELECT noise.*,
                   CASE kind
                       WHEN 0 THEN CASE WHEN g %% 8 = 0 THEN 'created' ELSE 'updated' END
                       WHEN 1 THEN 'challenge.status_transition'
                       WHEN 2 THEN 'module_completion.granted'
                       ELSE 'competency.evidence_link_changed'
                   END AS ev
            FROM noise
        ) AS n
        SQL, BENCH_NOISE_USERS, $from, $to - 1), [
        'tz1' => $tz, 'tz2' => $tz, 'tz3' => $tz, 'tz4' => $tz,
        'objective' => $objectiveMorph, 'challenge' => $challengeMorph,
        'module' => $moduleMorph, 'link' => $linkMorph, 'user' => $userMorph,
    ]);
}

DB::statement('SELECT setseed(0.015)');
benchInsertNoise(0, intdiv(BENCH_NOISE_ROWS, 2), $tz);

// ── Shared fixtures ──────────────────────────────────────────────────────
$plan = Plan::create([
    'code' => 'bench-personal',
    'client_type' => 'personal',
    'name' => ['es' => 'Bench personal'],
    'price_monthly' => 10,
    'price_yearly' => 100,
    'max_mentors' => 1,
    'entitlements' => ['mentor_workspace' => true],
    'is_active' => true,
]);

$admin = User::factory()->admin()->create(['email' => 'bench.admin@mis.test']);
$admin->assignRole('admin');
$admin->givePermissionTo('progress.view.support');

$mentors = [];
for ($m = 0; $m < intdiv(BENCH_SUBJECTS, BENCH_SUBJECTS_PER_MENTOR); $m++) {
    $mentor = User::factory()->mentor()->create(['email' => "bench.mentor{$m}@mis.test"]);
    $mentor->assignRole('mentor');
    $mentors[] = $mentor;
}

$pathA = LearningPath::create(['title' => ['es' => 'Ruta bench A'], 'is_active' => true]);
$pathB = LearningPath::create(['title' => ['es' => 'Ruta bench B'], 'is_active' => true]);
$modules = [];
foreach (range(1, 4) as $order) {
    $modules[] = PathModule::create(['learning_path_id' => $pathA->id, 'title' => "Módulo A{$order}", 'order' => $order]);
}
$modules[] = PathModule::create(['learning_path_id' => $pathB->id, 'title' => 'Módulo B1', 'order' => 1]);

$catalog = Assessment::create([
    'slug' => 'bench-catalog',
    'title' => ['es' => 'Catálogo bench'],
    'questions' => [['id' => 1, 'options' => ['a', 'b']]],
    'is_active' => true,
]);

$tags = SkillTag::query()->orderBy('name')->limit(12)->get()->values();
for ($i = $tags->count(); $i < 12; $i++) {
    $tags->push(SkillTag::create(['name' => "bench-tag-{$i}", 'category' => 'bench']));
}

$transition = app(TransitionChallengeStatusAction::class);
$linkModule = app(SetChallengePathModuleAction::class);
$setLink = app(SetCompetencyAssessmentLinkAction::class);
$retire = app(RetireEvidenceLinksForSourceAction::class);

// ── Subjects ─────────────────────────────────────────────────────────────
$subjects = [];

for ($s = 0; $s < BENCH_SUBJECTS; $s++) {
    $mentor = $mentors[intdiv($s, BENCH_SUBJECTS_PER_MENTOR)];

    // The subject's own clock: ~11 months ago, 37h13m per step, so the
    // whole timeline stays in the past; ties are steps that do not tick.
    $t = $realNow->subDays(330)->addMinutes($s * 7)->startOfSecond();
    Carbon::setTestNow($t);
    $tick = function () use (&$t): CarbonImmutable {
        $t = $t->addMinutes(37 * 60 + 13);
        Carbon::setTestNow($t);

        return $t;
    };

    DB::transaction(function () use (
        $s, $mentor, $admin, $plan, $pathA, $pathB, $modules, $catalog, $tags,
        $transition, $linkModule, $setLink, $retire, $realNow, &$t, $tick, &$subjects,
    ): void {
        $subject = User::factory()->create(['email' => "bench.subject{$s}@mis.test", 'client_type' => 'personal']);
        $subject->assignRole('mentee');

        $relationship = MentorshipRelationship::create([
            'type' => 'personal',
            'source_type' => 'user',
            'source_id' => $subject->id,
            'mentor_id' => $mentor->id,
            'status' => 'active',
            'started_at' => $t,
        ]);

        Subscription::create([
            'subscriber_type' => 'user',
            'subscriber_id' => $subject->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => $realNow->subYear(),
            'ends_at' => $realNow->addYear(),
        ]);

        // Learning paths: A started (+ completed at the end), B started.
        $progressA = UserLearningProgress::create([
            'user_id' => $subject->id, 'learning_path_id' => $pathA->id,
            'status' => 'in_progress', 'started_at' => $t,
        ]);
        UserLearningProgress::create([
            'user_id' => $subject->id, 'learning_path_id' => $pathB->id,
            'status' => 'in_progress', 'started_at' => $tick(),
        ]);

        // 20 individual objectives: created + 3 status changes + 2 evidences,
        // audited with the mentor as causer (as through the API).
        Auth::guard()->setUser($mentor);
        foreach (range(1, 20) as $o) {
            $tick();
            $objective = Objective::factory()->individual($subject)->create([
                'status' => 'not_started',
                'relationship_id' => $relationship->id,
                'created_by' => $mentor->id,
                'title' => "Objetivo bench {$o}",
            ]);
            foreach (['in_progress', $o % 2 === 0 ? 'achieved' : 'abandoned', 'in_progress'] as $status) {
                $tick();
                $objective->update(['status' => $status]);
            }
            foreach (range(1, 2) as $e) {
                $tick();
                ObjectiveEvidence::factory()->create(['objective_id' => $objective->id, 'user_id' => $subject->id]);
            }
        }
        Auth::forgetGuards();

        // 10 challenges: 1 module-link change + 3 transitions + 1 reviewed
        // submission each. Challenges 1–4 complete their own module
        // (4 grants); challenge 5 links to module 1 un-completed (1 revoke).
        // The link change, first transition and submission share an instant.
        foreach (range(1, 10) as $c) {
            $challenge = Challenge::factory()->forRelationship($relationship)->create([
                'status' => 'assigned',
                'title' => "Reto bench {$c}",
            ]);
            $tie = $tick();
            $module = $c <= 4 ? $modules[$c - 1] : ($c === 5 ? $modules[0] : $modules[4]);
            $linkModule->execute($challenge, $subject, $module->id, $tie);

            $steps = $c <= 4 ? ['abandoned', 'in_progress', 'completed'] : ['in_progress', 'abandoned', 'in_progress'];
            $transition->execute($challenge, $mentor, $steps[0], $tie);
            ChallengeSubmission::factory()->forChallenge($challenge)->create([
                'status' => 'reviewed',
                'submitted_at' => $tie,
                'reviewed_at' => $tie->addHours(5),
            ]);
            foreach (array_slice($steps, 1) as $to) {
                $transition->execute($challenge, $mentor, $to, $tick());
            }
        }

        // 6 competency links, each created and then changed (12 events).
        $result = AssessmentResult::create([
            'user_id' => $subject->id,
            'assessment_id' => $catalog->id,
            'answers' => [['question_id' => 1, 'option_index' => 0]],
            'score' => 80,
            'passed' => true,
            'completed_at' => $t,
        ]);
        foreach (range(0, 5) as $k) {
            $link = $setLink->execute($subject, $subject, null, 'assessment_result', $result->id, 'skill_tag', $tags[$k]->id, $tick());
            $setLink->execute($subject, $subject, $link, 'assessment_result', $result->id, 'skill_tag', $tags[$k + 6]->id, $tick());
        }

        // 4 deletions of a linked personality assessment through the audited
        // path: 3 still within retention, 1 expired (> 12 months) but not purged.
        foreach (['O', 'C', 'E', 'A'] as $i => $factor) {
            $at = $i === 0 ? $realNow->subDays(400)->addMinutes($s) : $tick();
            Carbon::setTestNow($at);
            $source = PersonalityAssessment::factory()->create(['user_id' => $subject->id, 'completed_at' => $at]);
            CompetencyAssessmentLink::factory()->forPersonalityAssessment($source)->forOceanFactor($factor)
                ->create(['user_id' => $subject->id]);
            DB::transaction(function () use ($retire, $source, $admin): void {
                $retire->execute($source, $admin);
                $source->delete();
            });
            Carbon::setTestNow($t);
        }

        $progressA->update(['status' => 'completed', 'completed_at' => $tick()]);

        $subjects[] = ['id' => $subject->id, 'mentor_index' => intdiv($s, BENCH_SUBJECTS_PER_MENTOR)];
    });

    Carbon::setTestNow();
    fwrite(STDOUT, sprintf("subject %02d seeded (%.1fs)\n", $s, microtime(true) - $started));
}

Carbon::setTestNow();
benchInsertNoise(intdiv(BENCH_NOISE_ROWS, 2), BENCH_NOISE_ROWS, $tz);
DB::statement('ANALYZE');

// ── Tokens for k6 (created with the real clock so they are not expired) ──
$userIds = array_column($subjects, 'id');
$tokens = [];
foreach (User::query()->whereIn('id', $userIds)->get()->keyBy('id') as $id => $user) {
    $tokens[$id] = $user->createToken('bench')->plainTextToken;
}

$benchMentor = $mentors[0];
$fixtures = [
    'subjects' => array_map(fn (string $id) => ['id' => $id, 'token' => $tokens[$id]], $userIds),
    'mentor' => [
        'token' => $benchMentor->createToken('bench')->plainTextToken,
        'mentee_ids' => array_values(array_column(array_filter($subjects, fn ($s) => $s['mentor_index'] === 0), 'id')),
    ],
    'support' => ['token' => $admin->createToken('bench')->plainTextToken],
];

file_put_contents(BENCH_OUT, json_encode($fixtures, JSON_PRETTY_PRINT));

$counts = DB::table('activity_log')
    ->selectRaw('log_name, count(*) AS n')
    ->groupBy('log_name')
    ->orderBy('log_name')
    ->pluck('n', 'log_name');
fwrite(STDOUT, 'activity_log by log_name: '.json_encode($counts).PHP_EOL);
fwrite(STDOUT, 'evidence_deletion_events: '.DB::table('evidence_deletion_events')->count().PHP_EOL);
fwrite(STDOUT, sprintf("done in %.1fs, fixtures at %s\n", microtime(true) - $started, BENCH_OUT));
