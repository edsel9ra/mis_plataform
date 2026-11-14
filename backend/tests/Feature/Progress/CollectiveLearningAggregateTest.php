<?php

use App\Http\Resources\CollectiveLearningAggregateResource;
use App\Models\Challenge;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\LearningPath;
use App\Models\Objective;
use App\Models\PathModule;
use App\Models\User;
use App\Models\UserLearningProgress;
use App\Models\UserModuleCompletion;
use App\Services\Progress\CollectiveProgressService;
use App\Services\Progress\Map\CollectiveLearningAggregate;
use Illuminate\Support\Facades\DB;

/**
 * Collective learning aggregate (RF-013.5–.8): comparable means of
 * individual activity/path rates over the same eligible base as the
 * baseline, with two independent privacy gates and no member-level data.
 * The baseline objective aggregate stays untouched.
 */

beforeEach(function () {
    $this->claggMentor = claggMember();
});

function claggMember(): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->create([
        'client_type' => 'personal',
        'is_active' => true,
    ]));
}

function claggCohort(User $manager): Cohort
{
    return Cohort::create([
        'created_by' => $manager->id,
        'name' => 'Cohorte '.fake()->word(),
        'status' => 'active',
    ]);
}

function claggJoin(Cohort $cohort, User $member): void
{
    CohortMember::create([
        'cohort_id' => $cohort->id,
        'user_id' => $member->id,
        'status' => 'active',
    ]);
}

function claggEligible(Cohort $cohort, User $member): void
{
    claggJoin($cohort, $member);
    Objective::factory()->individual($member)->create(['status' => 'in_progress']);
}

function claggPath(array $attributes = []): LearningPath
{
    return LearningPath::create(array_merge([
        'title' => ['es' => 'Ruta', 'en' => 'Path'],
        'is_active' => true,
    ], $attributes));
}

function claggModule(LearningPath $path, int $order = 1): PathModule
{
    return PathModule::create([
        'learning_path_id' => $path->id,
        'title' => "Módulo {$order}",
        'order' => $order,
    ]);
}

function claggProgress(User $subject, LearningPath $path): void
{
    UserLearningProgress::create([
        'user_id' => $subject->id,
        'learning_path_id' => $path->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);
}

function claggChallenge(User $mentee, User $mentor, PathModule $module, string $status): void
{
    Challenge::create([
        'mentor_id' => $mentor->id,
        'mentee_id' => $mentee->id,
        'title' => fake()->sentence(3),
        'status' => $status,
    ])->forceFill(['path_module_id' => $module->id])->save();
}

function claggCompletion(User $subject, PathModule $module): void
{
    UserModuleCompletion::create([
        'user_id' => $subject->id,
        'path_module_id' => $module->id,
        'completed_at' => now(),
    ]);
}

it('averages activity and path rates only across comparable contributions', function () {
    $manager = claggMember();
    $cohort = claggCohort($manager);
    $path = claggPath();
    $moduleA = claggModule($path, 1);
    $moduleB = claggModule($path, 2);

    // m1: activity 100 (2/2), path 100 (module A completed, B has no data)
    $m1 = claggMember();
    claggEligible($cohort, $m1);
    claggProgress($m1, $path);
    claggChallenge($m1, $this->claggMentor, $moduleA, 'completed');
    claggChallenge($m1, $this->claggMentor, $moduleA, 'completed');
    claggCompletion($m1, $moduleA);

    // m2: activity 50, path 100
    $m2 = claggMember();
    claggEligible($cohort, $m2);
    claggProgress($m2, $path);
    claggChallenge($m2, $this->claggMentor, $moduleA, 'completed');
    claggChallenge($m2, $this->claggMentor, $moduleA, 'in_progress');
    claggCompletion($m2, $moduleA);

    // m3: activity 0, path 0 (module B eligible, no record)
    $m3 = claggMember();
    claggEligible($cohort, $m3);
    claggProgress($m3, $path);
    claggChallenge($m3, $this->claggMentor, $moduleB, 'in_progress');
    claggChallenge($m3, $this->claggMentor, $moduleB, 'assigned');

    $aggregate = app(CollectiveProgressService::class)->learningAggregate('cohort', $cohort->id);

    expect($aggregate->state)->toBe(CollectiveLearningAggregate::STATE_AVAILABLE);
    expect($aggregate->suppressionReason)->toBeNull();
    expect($aggregate->activityRate->state)->toBe('value');
    expect($aggregate->activityRate->value)->toBe(50); // (100+50+0)/3
    expect($aggregate->rates)->toHaveCount(1);
    expect($aggregate->rates[0]->pathId)->toBe($path->id);
    expect($aggregate->rates[0]->versionKey)->toMatch('/^[0-9a-f]{32}$/');
    expect($aggregate->rates[0]->metric->state)->toBe('value');
    expect($aggregate->rates[0]->metric->value)->toBe(67); // (100+100+0)/3
});

it('suppresses the whole block when the contributing base is below the threshold', function () {
    $manager = claggMember();
    $cohort = claggCohort($manager);
    $path = claggPath();
    $module = claggModule($path);

    // Two contributing members + one active member WITHOUT objectives:
    // the eligible base stays at 2 even though the collective has 3 members.
    foreach (range(1, 2) as $i) {
        $member = claggMember();
        claggEligible($cohort, $member);
        claggProgress($member, $path);
        claggChallenge($member, $this->claggMentor, $module, 'completed');
        claggCompletion($member, $module);
    }

    claggJoin($cohort, claggMember());

    $aggregate = app(CollectiveProgressService::class)->learningAggregate('cohort', $cohort->id);

    expect($aggregate->state)->toBe(CollectiveLearningAggregate::STATE_SUPPRESSED);
    expect($aggregate->suppressionReason->value)->toBe('insufficient_base');
    expect($aggregate->activityRate)->toBeNull();
    expect($aggregate->rates)->toBe([]);
});

it('suppresses only the path below the contribution threshold while siblings stay calculable', function () {
    $manager = claggMember();
    $cohort = claggCohort($manager);
    $pathP = claggPath();
    $moduleP = claggModule($pathP);
    $pathQ = claggPath();
    $moduleQ = claggModule($pathQ);

    // m1/m2 contribute to both paths (rate 100) and activity 100;
    // m3 contributes only to path P (rate 0) and activity 0.
    foreach ([['P' => 'completed', 'Q' => 'completed'], ['P' => 'completed', 'Q' => 'completed']] as $statuses) {
        $member = claggMember();
        claggEligible($cohort, $member);
        claggProgress($member, $pathP);
        claggProgress($member, $pathQ);
        claggChallenge($member, $this->claggMentor, $moduleP, $statuses['P']);
        claggChallenge($member, $this->claggMentor, $moduleQ, $statuses['Q']);
        claggCompletion($member, $moduleP);
        claggCompletion($member, $moduleQ);
    }

    $m3 = claggMember();
    claggEligible($cohort, $m3);
    claggProgress($m3, $pathP);
    claggChallenge($m3, $this->claggMentor, $moduleP, 'in_progress');

    $aggregate = app(CollectiveProgressService::class)->learningAggregate('cohort', $cohort->id);

    expect($aggregate->state)->toBe(CollectiveLearningAggregate::STATE_AVAILABLE);
    expect($aggregate->activityRate->state)->toBe('value');
    expect($aggregate->activityRate->value)->toBe(67); // (100+100+0)/3
    expect($aggregate->rates)->toHaveCount(2);

    $byPath = collect($aggregate->rates)->keyBy('pathId');
    expect($byPath[$pathP->id]->metric->state)->toBe('value');
    expect($byPath[$pathP->id]->metric->value)->toBe(67);
    expect($byPath[$pathQ->id]->metric->state)->toBe('suppressed');
    expect($byPath[$pathQ->id]->metric->value)->toBeNull();
    expect($byPath[$pathQ->id]->metric->suppressionReason->value)->toBe('insufficient_metric_contributions');
});

it('never treats a missing individual rate as zero', function () {
    $manager = claggMember();
    $cohort = claggCohort($manager);
    $path = claggPath();
    $module = claggModule($path);

    // Three eligible members; only two consult the path. If the missing
    // member counted as zero the rate would be a mean of three — instead
    // two calculable contributions are below the threshold and suppress.
    foreach (range(1, 2) as $i) {
        $member = claggMember();
        claggEligible($cohort, $member);
        claggProgress($member, $path);
        claggChallenge($member, $this->claggMentor, $module, 'completed');
        claggCompletion($member, $module);
    }

    claggEligible($cohort, claggMember()); // no progress rows at all

    $aggregate = app(CollectiveProgressService::class)->learningAggregate('cohort', $cohort->id);

    expect($aggregate->state)->toBe(CollectiveLearningAggregate::STATE_AVAILABLE);
    expect($aggregate->rates[0]->metric->state)->toBe('suppressed');
    expect($aggregate->rates[0]->metric->suppressionReason->value)->toBe('insufficient_metric_contributions');
});

it('keeps different paths in separate version-keyed groups in a stable order', function () {
    $manager = claggMember();
    $cohort = claggCohort($manager);
    $pathA = claggPath(['title' => ['es' => 'Ruta A']]);
    $pathB = claggPath(['title' => ['es' => 'Ruta B']]);
    $moduleA = claggModule($pathA);
    $moduleB = claggModule($pathB);

    foreach (range(1, 3) as $i) {
        $member = claggMember();
        claggEligible($cohort, $member);
        claggProgress($member, $pathA);
        claggProgress($member, $pathB);
        claggChallenge($member, $this->claggMentor, $moduleA, 'completed');
        claggChallenge($member, $this->claggMentor, $moduleB, 'in_progress');
        claggCompletion($member, $moduleA);
    }

    $aggregate = app(CollectiveProgressService::class)->learningAggregate('cohort', $cohort->id);

    expect($aggregate->rates)->toHaveCount(2);
    expect(array_map(fn ($rate) => $rate->pathId, $aggregate->rates))
        ->toBe(collect([$pathA->id, $pathB->id])->sort()->values()->all());

    $byPath = collect($aggregate->rates)->keyBy('pathId');
    expect($byPath[$pathA->id]->metric->value)->toBe(100);
    expect($byPath[$pathA->id]->versionKey)
        ->not->toBe($byPath[$pathB->id]->versionKey);
    expect($byPath[$pathB->id]->metric->value)->toBe(0);
});

it('serializes suppression without member ids, counts or values', function () {
    $manager = claggMember();
    $cohort = claggCohort($manager);
    $path = claggPath();
    $module = claggModule($path);

    $members = [];

    // Eligible base of 3, only one calculable contribution: the block is
    // available but every metric is suppressed.
    $contributor = claggMember();
    $members[] = $contributor;
    claggEligible($cohort, $contributor);
    claggProgress($contributor, $path);
    claggChallenge($contributor, $this->claggMentor, $module, 'completed');
    claggCompletion($contributor, $module);

    foreach (range(1, 2) as $i) {
        $member = claggMember();
        $members[] = $member;
        claggEligible($cohort, $member);
    }

    $aggregate = app(CollectiveProgressService::class)->learningAggregate('cohort', $cohort->id);
    $payload = (new CollectiveLearningAggregateResource($aggregate))->toArray(request());
    $json = json_encode($payload);

    expect($aggregate->state)->toBe(CollectiveLearningAggregate::STATE_AVAILABLE);
    expect($payload['activity_rate'])->toBe([
        'state' => 'suppressed',
        'suppression_reason' => 'insufficient_metric_contributions',
    ]);
    expect($payload['rates'][0]['rate'])->toBe([
        'state' => 'suppressed',
        'suppression_reason' => 'insufficient_metric_contributions',
    ]);

    foreach ($members as $member) {
        expect($json)->not->toContain($member->id);
    }
});

it('keeps the baseline objective payload intact with learning data present', function () {
    $manager = claggMember();
    $cohort = claggCohort($manager);
    $path = claggPath();
    $module = claggModule($path);

    $members = [];

    foreach (range(1, 3) as $i) {
        $member = claggMember();
        $members[] = $member;
        claggJoin($cohort, $member);
        Objective::factory()->individual($member)->achieved()->create();
        Objective::factory()->individual($member)->create(['status' => 'in_progress']);
        claggProgress($member, $path);
        claggChallenge($member, $this->claggMentor, $module, 'completed');
        claggCompletion($member, $module);
    }

    $response = $this->actingAs($manager)
        ->getJson("/api/v1/progress/collectives/cohort/{$cohort->id}")
        ->assertOk()
        ->assertJsonPath('data.state', 'value')
        ->assertJsonPath('data.suppressed', false)
        ->assertJsonPath('data.metrics.mean', 50)
        ->assertJsonPath('data.metrics.sum.achieved', 3)
        ->assertJsonPath('data.metrics.sum.total', 6)
        ->assertJsonPath('data.base.eligible_members', 3)
        ->assertJsonPath('data.base.contributing_members', 3)
        ->assertJsonMissingPath('data.members')
        ->assertJsonMissingPath('data.metrics.members');

    foreach ($members as $member) {
        expect($response->getContent())->not->toContain($member->id);
    }
});

it('exposes the learning block additively on the collectives endpoint', function () {
    $manager = claggMember();
    $cohort = claggCohort($manager);
    $path = claggPath();
    $module = claggModule($path);

    foreach (range(1, 3) as $i) {
        $member = claggMember();
        claggEligible($cohort, $member);
        claggProgress($member, $path);
        claggChallenge($member, $this->claggMentor, $module, 'completed');
        claggCompletion($member, $module);
    }

    $response = $this->actingAs($manager)
        ->getJson("/api/v1/progress/collectives/cohort/{$cohort->id}")
        ->assertOk()
        ->assertJsonPath('data.state', 'value')
        ->assertJsonPath('data.learning.state', 'available')
        ->assertJsonPath('data.learning.suppression_reason', null)
        ->assertJsonPath('data.learning.activity_rate.state', 'value')
        ->assertJsonPath('data.learning.activity_rate.value', 100)
        ->assertJsonStructure([
            'data' => [
                'learning' => [
                    'state',
                    'suppression_reason',
                    'activity_rate' => ['state', 'value', 'suppression_reason'],
                    'rates' => [[
                        'path_id',
                        'version_key',
                        'title',
                        'rate' => ['state', 'value', 'suppression_reason'],
                    ]],
                ],
            ],
        ]);

    expect($response->json('data.learning.rates'))->toHaveCount(1);
    expect($response->json('data.learning.rates.0.path_id'))->toBe($path->id);
});

it('serializes the suppressed learning block without any metric or count', function () {
    $manager = claggMember();
    $cohort = claggCohort($manager);
    $path = claggPath();
    $module = claggModule($path);

    foreach (range(1, 2) as $i) {
        $member = claggMember();
        claggEligible($cohort, $member);
        claggProgress($member, $path);
        claggChallenge($member, $this->claggMentor, $module, 'completed');
        claggCompletion($member, $module);
    }

    $response = $this->actingAs($manager)
        ->getJson("/api/v1/progress/collectives/cohort/{$cohort->id}")
        ->assertOk();

    expect($response->json('data.learning'))->toBe([
        'state' => 'suppressed',
        'suppression_reason' => 'insufficient_base',
    ]);
});

it('keeps the panel collectives payload without the learning block', function () {
    $member = claggMember();
    $cohort = claggCohort(claggMember());
    claggJoin($cohort, $member);
    Objective::factory()->individual($member)->create(['status' => 'in_progress']);

    $this->actingAs($member)
        ->getJson('/api/v1/progress/panel')
        ->assertOk()
        ->assertJsonMissingPath('data.collectives.0.learning');
});

it('keeps the query count bounded regardless of members and paths', function () {
    $manager = claggMember();
    $cohort = claggCohort($manager);
    $pathA = claggPath();
    $pathB = claggPath();
    $moduleA = claggModule($pathA);
    $moduleB = claggModule($pathB);

    foreach (range(1, 4) as $i) {
        $member = claggMember();
        claggEligible($cohort, $member);
        claggProgress($member, $pathA);
        claggProgress($member, $pathB);
        claggChallenge($member, $this->claggMentor, $moduleA, 'completed');
        claggChallenge($member, $this->claggMentor, $moduleB, 'in_progress');
        claggCompletion($member, $moduleA);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(CollectiveProgressService::class)->learningAggregate('cohort', $cohort->id);
    $count = count(DB::getQueryLog());

    expect($count)->toBeLessThanOrEqual(10);
});
