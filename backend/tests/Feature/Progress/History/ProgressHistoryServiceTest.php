<?php

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
use App\Models\SkillTag;
use App\Models\User;
use App\Models\UserLearningProgress;
use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use App\Services\Progress\History\HistoryAdapter;
use App\Services\Progress\History\ProgressHistoryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * T015.6 — the page service: one merged, cursor-paginated timeline across
 * every feed (RF-015.1/.7/.8, plan §4 steps 2–4). Validation of the public
 * contract, defensive merge semantics and the undated annex.
 */
uses(RefreshDatabase::class)->group('progress');

beforeEach(function (): void {
    Role::findOrCreate('mentor');
    Role::findOrCreate('mentee');
    Role::findOrCreate('super_admin');

    $this->mentor = User::factory()->mentor()->create(['client_type' => 'personal']);
    $this->mentor->assignRole('mentor');
    $this->subject = User::factory()->create(['client_type' => 'personal']);
    $this->subject->assignRole('mentee');
    $this->admin = User::factory()->superAdmin()->create();
    $this->admin->assignRole('super_admin');
    $this->relationship = MentorshipRelationship::create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $this->subject->id,
        'mentor_id' => $this->mentor->id,
        'status' => 'active',
        'started_at' => now(),
    ]);

    $this->service = app(ProgressHistoryService::class);
});

function svcHistIds(array $events): array
{
    return array_column($events, 'id');
}

function svcHistSkillTag(string $name): SkillTag
{
    return SkillTag::create(['name' => $name, 'category' => 'test']);
}

function svcHistCatalogResult(User $user): AssessmentResult
{
    $assessment = Assessment::create([
        'slug' => 'svc-hist-'.Str::random(8),
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

function svcHistConsultableModule(User $subject): PathModule
{
    $path = LearningPath::create(['title' => ['es' => 'Ruta'], 'is_active' => true]);
    UserLearningProgress::create([
        'user_id' => $subject->id,
        'learning_path_id' => $path->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);

    return PathModule::create(['learning_path_id' => $path->id, 'title' => 'Módulo', 'order' => 1]);
}

/** Seeds at least one event per adapter feed, several at $tie. */
function svcHistDataset(object $test, User $subject, User $mentor, User $admin, MentorshipRelationship $relationship, CarbonImmutable $tie): void
{
    // objectives + objective evidence
    $objective = Objective::factory()->individual($subject)->create(['status' => 'not_started']);
    ObjectiveEvidence::factory()->create(['objective_id' => $objective->id, 'user_id' => $subject->id]);

    // challenges: transition + link change + module completion + submission
    $challenge = Challenge::factory()->forRelationship($relationship)->create(['status' => 'assigned']);
    app(TransitionChallengeStatusAction::class)->execute($challenge, $mentor, 'in_progress', $tie);
    $module = svcHistConsultableModule($subject);
    $done = Challenge::factory()->forRelationship($relationship)->create(['status' => 'completed']);
    app(SetChallengePathModuleAction::class)->execute($done, $subject, $module->id, $tie);
    ChallengeSubmission::factory()->forChallenge($challenge)->create();

    // competencies: link event + a hidden-actor deletion event
    app(SetCompetencyAssessmentLinkAction::class)->execute(
        $subject, $subject, null,
        'assessment_result', svcHistCatalogResult($subject)->id,
        'skill_tag', svcHistSkillTag('svc-tag-'.Str::random(8))->id, $tie,
    );
    $source = PersonalityAssessment::factory()->create([
        'user_id' => $subject->id,
        'completed_at' => now(),
    ]);
    CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($source)->forOceanFactor('O')
        ->create(['user_id' => $subject->id]);
    $test->travelTo($tie);
    $test->actingAs($admin)
        ->deleteJson("/api/v1/admin/assessments/{$source->id}")
        ->assertOk();
}

it('merges all feeds into one exactly-once cursor-paginated timeline', function (): void {
    svcHistDataset($this, $this->subject, $this->mentor, $this->admin, $this->relationship, CarbonImmutable::parse('2026-11-15T16:00:00Z'));

    $expected = svcHistIds($this->service->page($this->subject, null, 50)['data']);
    expect(count($expected))->toBeGreaterThanOrEqual(8);

    // One event per feed at minimum: verify the 8 prefixes are present.
    $prefixes = collect($expected)->map(fn (string $id) => explode(':', $id)[0])->unique()->sort()->values();
    expect($prefixes->all())->toBe([
        'challenge_activity',
        'challenge_submission',
        'competency_activity',
        'evidence_deletion',
        'learning_progress',
        'module_completion',
        'objective_activity',
        'objective_evidence',
    ]);

    foreach ([1, 2, 3] as $limit) {
        $seen = [];
        $undatedSeen = [];
        $cursor = null;
        $guard = 0;
        do {
            $page = $this->service->page($this->subject, $cursor, $limit);
            $seen = array_merge($seen, svcHistIds($page['data']));
            $undatedSeen = array_merge($undatedSeen, $page['undated']);
            $cursor = $page['next_cursor'];
        } while ($cursor !== null && ++$guard < 60);

        expect($seen)->toBe($expected);
        expect($undatedSeen)->toBe([]);
    }
});

it('caps, dedupes and sorts the undated annex only on the first page', function (): void {
    $undated = function (int $from, int $count): array {
        return array_map(
            fn (int $i) => ProgressHistoryCalculator::event(
                sprintf('u%03d', $i), 'fake', 'activities', null, ['kind' => 'system'],
            ),
            range($from, $from + $count - 1),
        );
    };
    $dated = [
        ProgressHistoryCalculator::event('d:2', 'fake', 'activities', CarbonImmutable::parse('2026-11-15T17:00:00Z'), null),
        ProgressHistoryCalculator::event('d:1', 'fake', 'activities', CarbonImmutable::parse('2026-11-15T16:00:00Z'), null),
    ];

    $fake = fn (array $datedFeed, array $undatedFeed) => new class($datedFeed, $undatedFeed) implements HistoryAdapter {
        public int $undatedCalls = 0;
        public ?int $lastCap = null;

        public function __construct(private array $datedFeed, private array $undatedFeed) {}

        public function dated(User $subject, ?array $after, int $limit): array
        {
            return $this->datedFeed;
        }

        public function undated(User $subject, int $cap): array
        {
            $this->undatedCalls++;
            $this->lastCap = $cap;

            return $this->undatedFeed;
        }
    };

    // 40 + 30 undated events; 'u040' is shared by both feeds.
    $fakeA = $fake($dated, $undated(1, 40));
    $fakeB = $fake([], $undated(40, 30));

    $service = new ProgressHistoryService([$fakeA, $fakeB]);

    $page = $service->page($this->subject, null, 1);

    expect($fakeA->lastCap)->toBe(ProgressHistoryService::UNDATED_CAP)
        ->and($fakeA->undatedCalls)->toBe(1)
        ->and($fakeB->undatedCalls)->toBe(1);

    $undatedIds = svcHistIds($page['undated']);
    expect($undatedIds)->toHaveCount(ProgressHistoryService::UNDATED_CAP)
        ->and($undatedIds)->toBe(array_values(array_unique($undatedIds)))
        ->and($undatedIds)->toBe(collect(range(1, 50))->map(fn (int $i) => sprintf('u%03d', $i))->all());
    foreach ($page['undated'] as $event) {
        expect($event['occurred_at'])->toBeNull();
    }

    // Follow-up pages never re-collect the annex.
    expect($page['next_cursor'])->not->toBeNull();
    $next = $service->page($this->subject, $page['next_cursor'], 1);
    expect($next['undated'])->toBe([])
        ->and($fakeA->undatedCalls)->toBe(1)
        ->and($fakeB->undatedCalls)->toBe(1);
});

it('rejects invalid limits and cursors before touching the feeds', function (): void {
    expect(fn () => $this->service->page($this->subject, null, 0))->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->service->page($this->subject, null, 51))->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->service->page($this->subject, 'not-a-cursor'))->toThrow(InvalidArgumentException::class);

    // A Carbon-parseable but non-canonical instant must be rejected before
    // any adapter SQL runs (a 22007 would otherwise surface as a 500).
    $badDate = rtrim(strtr(base64_encode(json_encode(['x', 'y'])), '+/', '-_'), '=');
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    expect(fn () => $this->service->page($this->subject, $badDate))->toThrow(InvalidArgumentException::class);
    expect($queries)->toBe(0);
});

it('isolates the merged timeline strictly to the subject', function (): void {
    $tie = CarbonImmutable::parse('2026-11-15T16:00:00Z');
    svcHistDataset($this, $this->subject, $this->mentor, $this->admin, $this->relationship, $tie);
    $ownIds = svcHistIds($this->service->page($this->subject, null, 50)['data']);

    // A second user with events in several sources.
    $other = User::factory()->create(['client_type' => 'personal']);
    $otherRel = MentorshipRelationship::create([
        'type' => 'personal', 'source_type' => 'user', 'source_id' => $other->id,
        'mentor_id' => $this->mentor->id, 'status' => 'active', 'started_at' => now(),
    ]);
    Objective::factory()->individual($other)->create();
    $otherChallenge = Challenge::factory()->forRelationship($otherRel)->create(['status' => 'assigned']);
    app(TransitionChallengeStatusAction::class)->execute($otherChallenge, $this->mentor, 'in_progress', now());
    ChallengeSubmission::factory()->forChallenge($otherChallenge)->create();
    UserLearningProgress::create([
        'user_id' => $other->id,
        'learning_path_id' => LearningPath::create(['title' => ['es' => 'R'], 'is_active' => true])->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);

    $after = svcHistIds($this->service->page($this->subject, null, 50)['data']);
    expect($after)->toBe($ownIds);
});

it('keeps a bounded query count per page regardless of volume', function (): void {
    $tie = CarbonImmutable::parse('2026-11-15T16:00:00Z');
    svcHistDataset($this, $this->subject, $this->mentor, $this->admin, $this->relationship, $tie);

    $count = function (): int {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $this->service->page($this->subject, null, 25);

        return $queries;
    };

    $small = $count();

    // Add several more events per feed.
    svcHistDataset($this, $this->subject, $this->mentor, $this->admin, $this->relationship, CarbonImmutable::parse('2026-11-15T18:00:00Z'));
    svcHistDataset($this, $this->subject, $this->mentor, $this->admin, $this->relationship, CarbonImmutable::parse('2026-11-15T19:00:00Z'));

    $big = $count();

    expect($big)->toBe($small)->and($big)->toBeLessThanOrEqual(25);
});
