<?php

use App\Actions\SetChallengePathModuleAction;
use App\Actions\SetCompetencyAssessmentLinkAction;
use App\Actions\TransitionChallengeStatusAction;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\Challenge;
use App\Models\LearningPath;
use App\Models\MentorshipRelationship;
use App\Models\Objective;
use App\Models\ObjectiveEvidence;
use App\Models\PathModule;
use App\Models\SkillTag;
use App\Models\User;
use App\Models\UserLearningProgress;
use App\Services\Progress\History\ChallengeActivityHistoryAdapter;
use App\Services\Progress\History\CompetencyHistoryAdapter;
use App\Services\Progress\History\ModuleCompletionHistoryAdapter;
use App\Services\Progress\History\ObjectiveEvidenceHistoryAdapter;
use App\Services\Progress\History\ObjectiveHistoryAdapter;
use App\Services\Progress\History\ProgressHistoryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * T015.10 — null dates must never reach the dated feed: the strict
 * mergePage() contract throws on them (one legacy null row would 500 the
 * whole history). Each adapter partitions in SQL — dated() only rows with
 * a resolved instant, undated() the complement — so a null created_at or a
 * missing properties.occurred_at lands in the undated annex instead.
 */
uses(RefreshDatabase::class)->group('progress');

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

    $this->objectives = new ObjectiveHistoryAdapter;
    $this->evidences = new ObjectiveEvidenceHistoryAdapter;
    $this->challenges = new ChallengeActivityHistoryAdapter;
    $this->completions = new ModuleCompletionHistoryAdapter;
    $this->competencies = new CompetencyHistoryAdapter;

    $this->transition = app(TransitionChallengeStatusAction::class);
    $this->setLink = app(SetChallengePathModuleAction::class);
    $this->setCompetency = app(SetCompetencyAssessmentLinkAction::class);
});

function undHistNullEvidence(string $evidenceId): void
{
    DB::table('objective_evidences')->where('id', $evidenceId)->update(['created_at' => null]);
}

function undHistNullActivity(int|string $activityId): void
{
    DB::table('activity_log')->where('id', $activityId)->update([
        'created_at' => null,
        'properties' => DB::raw("(properties::jsonb - 'occurred_at')::json"),
    ]);
}

function undHistActivityId(string $logName, string $event, callable $match): int
{
    $row = DB::table('activity_log')
        ->where('log_name', $logName)
        ->where('event', $event)
        ->orderByDesc('id')
        ->get()
        ->first(fn (object $r) => $match(json_decode((string) $r->properties, true) ?: []));

    expect($row)->not->toBeNull();

    return $row->id;
}

function undHistModule(User $subject): PathModule
{
    $path = LearningPath::create(['title' => ['es' => 'Ruta', 'en' => 'Path'], 'is_active' => true]);
    UserLearningProgress::create([
        'user_id' => $subject->id,
        'learning_path_id' => $path->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);

    return PathModule::create(['learning_path_id' => $path->id, 'title' => 'Módulo', 'order' => 1]);
}

function undHistChallenge(MentorshipRelationship $relationship, array $attributes = []): Challenge
{
    return Challenge::factory()->forRelationship($relationship)->create($attributes);
}

function undHistCatalogResult(User $user): AssessmentResult
{
    $assessment = Assessment::create([
        'slug' => 'und-hist-'.Str::random(8),
        'title' => ['es' => 'Catálogo'],
        'questions' => [['id' => 1, 'options' => ['a', 'b']]],
        'is_active' => true,
    ]);

    return AssessmentResult::create([
        'user_id' => $user->id,
        'assessment_id' => $assessment->id,
        'answers' => [['question_id' => 1, 'option_index' => 0]],
        'score' => 80,
        'passed' => true,
        'completed_at' => now(),
    ]);
}

it('objective evidence rows with null created_at land in undated, not the dated feed', function (): void {
    $this->actingAs($this->subject);
    $objective = Objective::factory()->individual($this->subject)->create();
    $evidence = ObjectiveEvidence::factory()->create([
        'objective_id' => $objective->id,
        'user_id' => $this->subject->id,
    ]);
    $datedEvidence = ObjectiveEvidence::factory()->create([
        'objective_id' => $objective->id,
        'user_id' => $this->subject->id,
    ]);

    $expected = $this->evidences->dated($this->subject, null, 25);
    $expectedEvent = collect($expected)->firstWhere('id', 'objective_evidence:'.$evidence->id);
    expect($expectedEvent)->not->toBeNull()
        ->and($expectedEvent['type'])->toBe('objective.evidence_added');

    undHistNullEvidence($evidence->id);

    $datedIds = array_column($this->evidences->dated($this->subject, null, 25), 'id');
    expect($datedIds)->toBe(['objective_evidence:'.$datedEvidence->id]);

    $undated = $this->evidences->undated($this->subject, 25);
    expect($undated)->toHaveCount(1)
        ->and($undated[0]['id'])->toBe($expectedEvent['id'])
        ->and($undated[0]['type'])->toBe($expectedEvent['type'])
        ->and($undated[0]['occurred_at'])->toBeNull()
        ->and($undated[0]['incomplete'])->toContain('occurred_at');
});

it('objective activity rows with null created_at land in undated, not the dated feed', function (): void {
    $this->actingAs($this->subject);
    $objective = Objective::factory()->individual($this->subject)->create();
    $objective->update(['status' => 'in_progress']); // keeps one dated event

    $expected = $this->objectives->dated($this->subject, null, 25);
    $created = collect($expected)->firstWhere('type', 'objective.created');
    expect($created)->not->toBeNull();

    $activityId = undHistActivityId('objective', 'created', fn (array $p) => true);
    undHistNullActivity($activityId);

    $dated = $this->objectives->dated($this->subject, null, 25);
    expect(array_column($dated, 'id'))->not->toContain($created['id'])
        ->and(collect($dated)->every(fn (array $e) => $e['occurred_at'] !== null))->toBeTrue();

    $undated = $this->objectives->undated($this->subject, 25);
    expect($undated)->toHaveCount(1)
        ->and($undated[0]['id'])->toBe($created['id'])
        ->and($undated[0]['type'])->toBe('objective.created')
        ->and($undated[0]['occurred_at'])->toBeNull();
});

it('challenge activity rows with no resolvable instant land in undated', function (): void {
    $challenge = undHistChallenge($this->relationship, ['status' => 'assigned']);
    $this->transition->execute($challenge, $this->mentor, 'in_progress', CarbonImmutable::parse('2026-11-15T16:00:00Z'));
    $this->transition->execute($challenge, $this->mentor, 'completed', CarbonImmutable::parse('2026-11-15T17:00:00Z'));

    $expected = $this->challenges->dated($this->subject, null, 25);
    expect($expected)->toHaveCount(2);
    $first = $expected[0]; // newest: completed

    $activityId = undHistActivityId(
        'challenge',
        'challenge.status_transition',
        fn (array $p) => ($p['after']['status'] ?? null) === 'completed',
    );
    undHistNullActivity($activityId);

    $dated = $this->challenges->dated($this->subject, null, 25);
    expect($dated)->toHaveCount(1)
        ->and($dated[0]['after'])->toBe(['status' => 'in_progress']);

    $undated = $this->challenges->undated($this->subject, 25);
    expect($undated)->toHaveCount(1)
        ->and($undated[0]['id'])->toBe($first['id'])
        ->and($undated[0]['type'])->toBe('challenge.status_transition')
        ->and($undated[0]['occurred_at'])->toBeNull()
        ->and($undated[0]['after'])->toBe(['status' => 'completed']);
});

it('module completion rows with no resolvable instant land in undated', function (): void {
    $module = undHistModule($this->subject);
    $done = undHistChallenge($this->relationship, ['status' => 'completed']);
    $this->setLink->execute($done, $this->subject, $module->id, CarbonImmutable::parse('2026-11-15T16:00:00Z'));

    $expected = $this->completions->dated($this->subject, null, 25);
    expect($expected)->toHaveCount(1)
        ->and($expected[0]['type'])->toBe('module_completion.granted');

    $activityId = undHistActivityId('module_completion', 'module_completion.granted', fn (array $p) => true);
    undHistNullActivity($activityId);

    expect($this->completions->dated($this->subject, null, 25))->toBe([]);

    $undated = $this->completions->undated($this->subject, 25);
    expect($undated)->toHaveCount(1)
        ->and($undated[0]['id'])->toBe($expected[0]['id'])
        ->and($undated[0]['type'])->toBe('module_completion.granted')
        ->and($undated[0]['occurred_at'])->toBeNull()
        ->and($undated[0]['after']['result'])->toBe('completed');
});

it('competency rows with no resolvable instant land in undated, keeping the reason exclusion', function (): void {
    $tag = SkillTag::create(['name' => 'Liderazgo', 'category' => 'test']);
    $result = undHistCatalogResult($this->subject);

    $link = $this->setCompetency->execute(
        $this->subject, $this->subject, null,
        'assessment_result', $result->id, 'skill_tag', $tag->id,
        CarbonImmutable::parse('2026-11-15T16:00:00Z'),
    );

    $expected = $this->competencies->dated($this->subject, null, 25);
    expect($expected)->toHaveCount(1)
        ->and($expected[0]['type'])->toBe('competency.evidence_link_changed');

    $activityId = undHistActivityId(
        'progress',
        'competency.evidence_link_changed',
        fn (array $p) => ($p['link_id'] ?? null) === $link->id,
    );
    undHistNullActivity($activityId);

    expect($this->competencies->dated($this->subject, null, 25))->toBe([]);

    $undated = $this->competencies->undated($this->subject, 25);
    expect($undated)->toHaveCount(1)
        ->and($undated[0]['id'])->toBe($expected[0]['id'])
        ->and($undated[0]['type'])->toBe('competency.evidence_link_changed')
        ->and($undated[0]['occurred_at'])->toBeNull()
        ->and($undated[0]['after']['competency_name'])->toBe('Liderazgo');
});

it('serves undated events through the service and the API without breaking dated pagination', function (): void {
    $this->actingAs($this->subject);
    $objective = Objective::factory()->individual($this->subject)->create(['status' => 'not_started']);
    $objective->update(['status' => 'in_progress']);
    $objective->update(['status' => 'achieved']);
    $evidence = ObjectiveEvidence::factory()->create([
        'objective_id' => $objective->id,
        'user_id' => $this->subject->id,
    ]);

    undHistNullEvidence($evidence->id);

    $service = app(ProgressHistoryService::class);
    $page = $service->page($this->subject, null, 25);

    expect(collect($page['undated'])->pluck('id')->all())
        ->toContain('objective_evidence:'.$evidence->id);
    expect(collect($page['data'])->pluck('id')->all())
        ->not->toContain('objective_evidence:'.$evidence->id);

    // limit-1 pagination still visits every dated event exactly once.
    $seen = [];
    $cursor = null;
    do {
        $page = $service->page($this->subject, $cursor, 1);
        foreach ($page['data'] as $event) {
            $seen[] = $event['id'];
        }
        $cursor = $page['next_cursor'];
    } while ($cursor !== null);

    expect($seen)->toHaveCount(count(array_unique($seen)))
        ->toHaveCount(3); // created + in_progress + achieved
    expect($seen)->not->toContain('objective_evidence:'.$evidence->id);

    $response = $this->actingAs($this->subject)
        ->getJson('/api/v1/progress/history')
        ->assertOk();
    expect(collect($response->json('undated'))->pluck('id')->all())
        ->toContain('objective_evidence:'.$evidence->id);

    // A cursor page never carries the undated annex.
    $first = $this->actingAs($this->subject)
        ->getJson('/api/v1/progress/history?limit=1')->assertOk();
    $next = $this->actingAs($this->subject)
        ->getJson('/api/v1/progress/history?limit=1&cursor='.urlencode((string) $first->json('next_cursor')))
        ->assertOk();
    expect($next->json('undated'))->toBe([]);
});

it('never leaks another subject\'s undated events', function (): void {
    $this->actingAs($this->subject);
    $objective = Objective::factory()->individual($this->subject)->create();
    $evidence = ObjectiveEvidence::factory()->create([
        'objective_id' => $objective->id,
        'user_id' => $this->subject->id,
    ]);
    undHistNullEvidence($evidence->id);

    $other = User::factory()->create();
    $otherObjective = Objective::factory()->individual($other)->create();
    $otherEvidence = ObjectiveEvidence::factory()->create([
        'objective_id' => $otherObjective->id,
        'user_id' => $other->id,
    ]);
    undHistNullEvidence($otherEvidence->id);

    $service = app(ProgressHistoryService::class);
    $page = $service->page($this->subject, null, 25);

    expect(collect($page['undated'])->pluck('id')->all())
        ->toBe(['objective_evidence:'.$evidence->id]);
});
