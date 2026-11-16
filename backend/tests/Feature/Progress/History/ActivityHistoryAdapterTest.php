<?php

use App\Actions\SetChallengePathModuleAction;
use App\Actions\TransitionChallengeStatusAction;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\LearningPath;
use App\Models\MentorshipRelationship;
use App\Models\PathModule;
use App\Models\User;
use App\Models\UserLearningProgress;
use App\Models\UserModuleCompletion;
use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use App\Services\Progress\History\ChallengeActivityHistoryAdapter;
use App\Services\Progress\History\ChallengeSubmissionHistoryAdapter;
use App\Services\Progress\History\LearningPathHistoryAdapter;
use App\Services\Progress\History\ModuleCompletionHistoryAdapter;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * T015.3 — history adapters for challenges, challenge-module links, module
 * completions, submissions and learning paths (RF-015.3/.7/.8, plan §1-§2).
 * Every event is a projection of a verified record: no status is inferred,
 * no actor is guessed, no notes or free text ever leave the sources.
 */
uses(RefreshDatabase::class)->group('progress');

beforeEach(function (): void {
    Role::findOrCreate('mentor');
    Role::findOrCreate('mentee');

    $this->mentor = User::factory()->mentor()->create(['client_type' => 'personal']);
    $this->mentor->assignRole('mentor');
    $this->mentee = User::factory()->create(['client_type' => 'personal']);
    $this->mentee->assignRole('mentee');
    $this->relationship = MentorshipRelationship::create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $this->mentee->id,
        'mentor_id' => $this->mentor->id,
        'status' => 'active',
        'started_at' => now(),
    ]);

    $this->transition = app(TransitionChallengeStatusAction::class);
    $this->setLink = app(SetChallengePathModuleAction::class);
    $this->challenges = new ChallengeActivityHistoryAdapter;
    $this->completions = new ModuleCompletionHistoryAdapter;
    $this->submissions = new ChallengeSubmissionHistoryAdapter;
    $this->paths = new LearningPathHistoryAdapter;
});

function actHistIds(array $events): array
{
    return array_column($events, 'id');
}

function actHistPath(array $title = ['es' => 'Ruta', 'en' => 'Path']): LearningPath
{
    return LearningPath::create(['title' => $title, 'is_active' => true]);
}

function actHistModule(LearningPath $path, string $title = 'Módulo'): PathModule
{
    return PathModule::create([
        'learning_path_id' => $path->id,
        'title' => $title,
        'order' => 1,
    ]);
}

function actHistConsultableModule(User $subject, string $title = 'Módulo'): PathModule
{
    $path = actHistPath();
    UserLearningProgress::create([
        'user_id' => $subject->id,
        'learning_path_id' => $path->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);

    return actHistModule($path, $title);
}

function actHistChallenge(MentorshipRelationship $relationship, array $attributes = []): Challenge
{
    return Challenge::factory()->forRelationship($relationship)->create($attributes);
}

it('emits status transitions newest-first with actor and whitelisted before/after', function (): void {
    $challenge = actHistChallenge($this->relationship, [
        'status' => 'assigned',
        'title' => 'Reto uno',
        'description' => 'desc privada',
        'instructions' => 'instr privada',
    ]);

    $this->travelTo(CarbonImmutable::parse('2026-11-15T16:00:00Z'));
    $this->transition->execute($challenge, $this->mentor, 'in_progress', CarbonImmutable::parse('2026-11-15T16:00:00Z'));
    $this->travelTo(CarbonImmutable::parse('2026-11-15T17:00:00Z'));
    $this->transition->execute($challenge, $this->mentor, 'completed', CarbonImmutable::parse('2026-11-15T17:00:00Z'));

    $events = $this->challenges->dated($this->mentee, null, 25);

    expect($events)->toHaveCount(2);
    expect($events[0]['type'])->toBe('challenge.status_transition')
        ->and($events[0]['domain'])->toBe('activities')
        ->and($events[0]['before'])->toBe(['status' => 'in_progress'])
        ->and($events[0]['after'])->toBe(['status' => 'completed'])
        ->and($events[0]['actor'])->toBe(['id' => $this->mentor->id, 'name' => $this->mentor->name])
        ->and($events[0]['subject_ref'])->toBe(['challenge_id' => $challenge->id, 'title' => 'Reto uno'])
        ->and($events[0]['id'])->toStartWith('challenge_activity:')
        ->and($events[0]['incomplete'])->toBe([]);
    expect($events[1]['before'])->toBe(['status' => 'assigned'])
        ->and($events[1]['after'])->toBe(['status' => 'in_progress']);

    $json = json_encode($events);
    expect($json)->not->toContain('desc privada')->not->toContain('instr privada');
});

it('never infers events the sources do not record', function (): void {
    $challenge = actHistChallenge($this->relationship, ['status' => 'assigned']);
    $challenge->forceFill(['status' => 'completed'])->saveQuietly();

    expect($this->challenges->dated($this->mentee, null, 25))->toBe([]);

    $module = actHistModule(actHistPath());
    UserModuleCompletion::create([
        'user_id' => $this->mentee->id,
        'path_module_id' => $module->id,
        'completed_at' => now(),
    ]);
    expect($this->completions->dated($this->mentee, null, 25))->toBe([]);

    $path = actHistPath();
    UserLearningProgress::create([
        'user_id' => $this->mentee->id,
        'learning_path_id' => $path->id,
        'status' => 'completed',
        'started_at' => now(),
        'completed_at' => null,
    ]);
    $events = $this->paths->dated($this->mentee, null, 25);
    expect($events)->toHaveCount(1);
    expect($events[0]['type'])->toBe('learning_path.started');
});

it('emits link created and removed with null boundaries and actor, then teardown with reason', function (): void {
    $this->actingAs($this->mentor);
    $module = actHistConsultableModule($this->mentee);
    $challenge = actHistChallenge($this->relationship, ['status' => 'assigned']);

    $this->setLink->execute($challenge, $this->mentee, $module->id, now());
    $this->setLink->execute($challenge, $this->mentee, null, now());

    $events = $this->challenges->dated($this->mentee, null, 25);
    expect($events)->toHaveCount(2);

    expect($events[0]['type'])->toBe('challenge.module_link_changed')
        ->and($events[0]['before'])->toBe(['path_module_id' => $module->id])
        ->and($events[0]['after'])->toBe(['path_module_id' => null])
        ->and($events[0]['actor'])->toBe(['id' => $this->mentee->id, 'name' => $this->mentee->name])
        ->and($events[0]['incomplete'])->toBe([]);
    expect($events[1]['before'])->toBe(['path_module_id' => null])
        ->and($events[1]['after'])->toBe(['path_module_id' => $module->id]);

    // Module teardown: the observer audits the link removal with a reason.
    $this->setLink->execute($challenge, $this->mentee, $module->id, now());
    $module->delete();

    $events = $this->challenges->dated($this->mentee, null, 25);
    $teardown = $events[0];

    expect($teardown['type'])->toBe('challenge.module_link_changed')
        ->and($teardown['before'])->toBe(['path_module_id' => $module->id])
        ->and($teardown['after'])->toBe(['path_module_id' => null, 'reason' => 'path_module_deleted'])
        ->and($teardown['actor'])->toBe(['id' => $this->mentor->id, 'name' => $this->mentor->name]);
});

it('emits module completion granted and revoked with system actor and trigger', function (): void {
    $module = actHistConsultableModule($this->mentee);
    $done = actHistChallenge($this->relationship, ['status' => 'completed']);
    $abandoned = actHistChallenge($this->relationship, ['status' => 'abandoned']);

    // Link the abandoned one quietly; grant through a real link action.
    $abandoned->forceFill(['path_module_id' => $module->id])->save();
    $this->setLink->execute($done, $this->mentee, $module->id, now());

    // Reactivating the abandoned challenge makes the module incomplete → revoke.
    $this->transition->execute($abandoned, $this->mentor, 'in_progress', now());

    $events = $this->completions->dated($this->mentee, null, 25);

    expect($events)->toHaveCount(2);

    expect($events[0]['type'])->toBe('module_completion.revoked')
        ->and($events[0]['domain'])->toBe('activities')
        ->and($events[0]['actor'])->toBe(['kind' => 'system'])
        ->and($events[0]['before'])->toBeNull()
        ->and($events[0]['after']['result'])->toBe('not_completed')
        ->and($events[0]['after']['triggered_by'])->toBe([
            'event' => 'challenge.status_transition',
            'actor_id' => $this->mentor->id,
            'challenge_id' => $abandoned->id,
        ])
        ->and($events[0]['subject_ref']['path_module_id'])->toBe($module->id)
        ->and($events[0]['subject_ref']['title'])->toBe('Módulo')
        ->and($events[0]['incomplete'])->toBe([]);

    expect($events[1]['type'])->toBe('module_completion.granted')
        ->and($events[1]['after']['result'])->toBe('completed')
        ->and($events[1]['after']['triggered_by']['event'])->toBe('challenge.module_link_changed')
        ->and($events[1]['after']['triggered_by']['actor_id'])->toBe($this->mentee->id)
        ->and($events[1]['after']['triggered_by']['challenge_id'])->toBe($done->id);
});

it('emits a teardown revocation with null challenge_id and still complete fields', function (): void {
    $this->actingAs($this->mentor);
    $module = actHistConsultableModule($this->mentee);
    $done = actHistChallenge($this->relationship, ['status' => 'completed']);
    $this->setLink->execute($done, $this->mentee, $module->id, now());

    $module->delete();

    $events = $this->completions->dated($this->mentee, null, 25);

    $teardown = collect($events)->firstWhere('type', 'module_completion.revoked');
    expect($teardown)->not->toBeNull()
        ->and($teardown['after']['triggered_by']['event'])->toBe('path_module.deleted')
        ->and($teardown['after']['triggered_by']['challenge_id'])->toBeNull()
        ->and($teardown['incomplete'])->not->toContain('triggered_by');
});

it('isolates events strictly to the subject', function (): void {
    $other = User::factory()->create(['client_type' => 'personal']);
    $other->assignRole('mentee');
    $otherRelationship = MentorshipRelationship::create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $other->id,
        'mentor_id' => $this->mentor->id,
        'status' => 'active',
        'started_at' => now(),
    ]);

    $otherChallenge = actHistChallenge($otherRelationship, ['status' => 'assigned']);
    $this->transition->execute($otherChallenge, $this->mentor, 'in_progress', now());

    $module = actHistConsultableModule($other);
    $done = actHistChallenge($otherRelationship, ['status' => 'completed']);
    $this->setLink->execute($done, $other, $module->id, now());

    ChallengeSubmission::factory()->forChallenge($otherChallenge)->create();
    $otherPath = actHistPath();
    UserLearningProgress::create([
        'user_id' => $other->id,
        'learning_path_id' => $otherPath->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);

    expect($this->challenges->dated($this->mentee, null, 25))->toBe([]);
    expect($this->completions->dated($this->mentee, null, 25))->toBe([]);
    expect($this->submissions->dated($this->mentee, null, 25))->toBe([]);
    expect($this->paths->dated($this->mentee, null, 25))->toBe([]);

    expect($this->challenges->dated($other, null, 25))->not->toBe([]);
});

it('emits submitted and reviewed submission events without notes or status', function (): void {
    $challenge = actHistChallenge($this->relationship, ['status' => 'in_progress', 'title' => 'Reto entrega']);

    $this->travelTo(CarbonImmutable::parse('2026-11-15T16:00:00Z'));
    $unreviewed = ChallengeSubmission::factory()->forChallenge($challenge)->create(['note' => 'nota secreta']);
    $reviewed = ChallengeSubmission::factory()->forChallenge($challenge)->create([
        'note' => 'otra nota secreta',
        'reviewed_at' => '2026-11-15 11:00:00',
    ]);

    $events = $this->submissions->dated($this->mentee, null, 25);

    expect($events)->toHaveCount(3);

    $reviewedEvent = $events[0];
    expect($reviewedEvent['type'])->toBe('challenge_submission.reviewed')
        ->and($reviewedEvent['domain'])->toBe('activities')
        ->and($reviewedEvent['id'])->toBe("challenge_submission:{$reviewed->id}:reviewed")
        ->and($reviewedEvent['occurred_at']->eq(CarbonImmutable::parse('2026-11-15T17:00:00Z')))->toBeTrue()
        ->and($reviewedEvent['subject_ref']['submission_id'])->toBe($reviewed->id)
        ->and($reviewedEvent['subject_ref']['challenge_id'])->toBe($challenge->id)
        ->and($reviewedEvent['subject_ref']['title'])->toBe('Reto entrega');

    $submittedIds = collect($events)->where('type', 'challenge_submission.submitted')->pluck('id')->all();
    expect($submittedIds)->toContain("challenge_submission:{$reviewed->id}:submitted")
        ->toContain("challenge_submission:{$unreviewed->id}:submitted");

    foreach ($events as $event) {
        expect($event['actor'])->toBeNull();
        expect($event['incomplete'])->toContain('actor');
        $json = json_encode($event);
        expect($json)->not->toContain('nota secreta')
            ->not->toContain('otra nota secreta')
            ->not->toContain('"note"')
            ->not->toContain('"status"');
    }
});

it('cannot represent an undated submission and reports none', function (): void {
    $challenge = actHistChallenge($this->relationship, ['status' => 'in_progress']);

    expect(fn () => DB::table('challenge_submissions')->insert([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'challenge_id' => $challenge->id,
        'relationship_id' => $this->relationship->id,
        'mentee_id' => $this->mentee->id,
        'submitted_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect($this->submissions->undated($this->mentee, 10))->toBe([]);
});

it('emits started and completed path events with the i18n title only', function (): void {
    $onlyStarted = actHistPath();
    $both = actHistPath();
    $none = actHistPath();

    UserLearningProgress::create([
        'user_id' => $this->mentee->id,
        'learning_path_id' => $onlyStarted->id,
        'status' => 'in_progress',
        'started_at' => '2026-11-15T10:00:00Z',
    ]);
    UserLearningProgress::create([
        'user_id' => $this->mentee->id,
        'learning_path_id' => $both->id,
        'status' => 'completed',
        'started_at' => '2026-11-14T10:00:00Z',
        'completed_at' => '2026-11-15T12:00:00Z',
        'progress' => 100,
        'current_module_id' => actHistModule($both)->id,
    ]);
    UserLearningProgress::create([
        'user_id' => $this->mentee->id,
        'learning_path_id' => $none->id,
        'status' => 'not_started',
    ]);

    $events = $this->paths->dated($this->mentee, null, 25);

    expect($events)->toHaveCount(3);
    expect($events[0]['type'])->toBe('learning_path.completed')
        ->and($events[0]['subject_ref']['learning_path_id'])->toBe($both->id)
        ->and($events[0]['subject_ref']['title'])->toEqual(['es' => 'Ruta', 'en' => 'Path'])
        ->and($events[0]['actor'])->toBeNull()
        ->and($events[0]['incomplete'])->toContain('actor');
    expect(collect($events)->pluck('type')->all())->toBe([
        'learning_path.completed',
        'learning_path.started',
        'learning_path.started',
    ]);

    $json = json_encode($events);
    expect($json)->not->toContain('"progress"')
        ->not->toContain('"status"')
        ->not->toContain('current_module_id');

    // Another user's progress is never included.
    $other = User::factory()->create();
    UserLearningProgress::create([
        'user_id' => $other->id,
        'learning_path_id' => actHistPath()->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);
    expect($this->paths->dated($this->mentee, null, 25))->toHaveCount(3);
});

it('orders by properties.occurred_at before created_at', function (): void {
    $challenge = actHistChallenge($this->relationship, ['status' => 'assigned']);

    // First write has the LATER occurred_at; second write the earlier one.
    $this->travelTo(CarbonImmutable::parse('2026-11-15T16:00:00Z'));
    $this->transition->execute($challenge, $this->mentor, 'in_progress', CarbonImmutable::parse('2026-11-15T18:00:00Z'));
    $this->travelTo(CarbonImmutable::parse('2026-11-15T17:00:00Z'));
    $this->transition->execute($challenge, $this->mentor, 'completed', CarbonImmutable::parse('2026-11-15T15:00:00Z'));

    $events = $this->challenges->dated($this->mentee, null, 25);

    expect($events)->toHaveCount(2);
    expect($events[0]['after'])->toBe(['status' => 'in_progress'])
        ->and($events[0]['occurred_at']->eq(CarbonImmutable::parse('2026-11-15T18:00:00Z')))->toBeTrue();
    expect($events[1]['after'])->toBe(['status' => 'completed'])
        ->and($events[1]['occurred_at']->eq(CarbonImmutable::parse('2026-11-15T15:00:00Z')))->toBeTrue();

    $sorted = $events;
    usort($sorted, fn (array $a, array $b): int => ProgressHistoryCalculator::compare($a, $b));
    expect(actHistIds($sorted))->toBe(actHistIds($events));
});

it('pages through all four adapters exactly once in compare() order', function (): void {
    $this->actingAs($this->mentor);
    $module = actHistConsultableModule($this->mentee);
    $challenge = actHistChallenge($this->relationship, ['status' => 'assigned']);

    // Several events share the same instant across adapters.
    $this->travelTo(CarbonImmutable::parse('2026-11-15T16:00:00Z'));
    $this->transition->execute($challenge, $this->mentor, 'in_progress', CarbonImmutable::parse('2026-11-15T16:00:00Z'));
    $this->setLink->execute($challenge, $this->mentee, $module->id, CarbonImmutable::parse('2026-11-15T16:00:00Z'));
    ChallengeSubmission::factory()->forChallenge($challenge)->create();
    $path = actHistPath();
    UserLearningProgress::create([
        'user_id' => $this->mentee->id,
        'learning_path_id' => $path->id,
        'status' => 'in_progress',
        'started_at' => '2026-11-15T10:00:00',
    ]);

    $this->travelTo(CarbonImmutable::parse('2026-11-15T17:00:00Z'));
    $this->transition->execute($challenge, $this->mentor, 'completed', CarbonImmutable::parse('2026-11-15T17:00:00Z'));

    $feeds = fn (?array $after, int $limit): array => [
        $this->challenges->dated($this->mentee, $after, $limit),
        $this->completions->dated($this->mentee, $after, $limit),
        $this->submissions->dated($this->mentee, $after, $limit),
        $this->paths->dated($this->mentee, $after, $limit),
    ];

    $expected = actHistIds(ProgressHistoryCalculator::mergePage($feeds(null, 50), null, 50)['data']);
    expect(count($expected))->toBeGreaterThanOrEqual(5);

    foreach ([1, 2] as $limit) {
        $seen = [];
        $after = null;
        $guard = 0;

        do {
            $page = ProgressHistoryCalculator::mergePage($feeds($after, $limit), $after, $limit);
            $seen = array_merge($seen, actHistIds($page['data']));
            $after = $page['next_cursor'] !== null
                ? ProgressHistoryCalculator::decodeCursor($page['next_cursor'])
                : null;
        } while ($after !== null && ++$guard < 30);

        expect($seen)->toBe($expected);
    }
});

it('uses a constant number of queries per dated() call', function (): void {
    $this->actingAs($this->mentor);
    $module = actHistConsultableModule($this->mentee);
    $challenge = actHistChallenge($this->relationship, ['status' => 'assigned']);
    $this->transition->execute($challenge, $this->mentor, 'in_progress', now());
    ChallengeSubmission::factory()->forChallenge($challenge)->create();
    UserLearningProgress::create([
        'user_id' => $this->mentee->id,
        'learning_path_id' => actHistPath()->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);

    $count = function (object $adapter): int {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        return tap(0, fn () => $adapter->dated($this->mentee, null, 25)) && $queries;
    };

    $small = [
        'challenges' => $count($this->challenges),
        'completions' => $count($this->completions),
        'submissions' => $count($this->submissions),
        'paths' => $count($this->paths),
    ];

    // Grow every source well beyond one page.
    $this->transition->execute($challenge, $this->mentor, 'completed', now());
    $done = actHistChallenge($this->relationship, ['status' => 'completed']);
    $this->setLink->execute($done, $this->mentee, $module->id, now());
    ChallengeSubmission::factory()->forChallenge($challenge)->count(4)->create();
    foreach (range(1, 4) as $i) {
        $p = actHistPath();
        UserLearningProgress::create([
            'user_id' => $this->mentee->id,
            'learning_path_id' => $p->id,
            'status' => 'completed',
            'started_at' => now()->subDays($i + 1),
            'completed_at' => now()->subDays($i),
        ]);
    }

    $big = [
        'challenges' => $count($this->challenges),
        'completions' => $count($this->completions),
        'submissions' => $count($this->submissions),
        'paths' => $count($this->paths),
    ];

    foreach ($big as $name => $countNow) {
        expect($countNow)->toBe($small[$name], "{$name} query count changed with row count")
            ->and($countNow)->toBeLessThanOrEqual(4);
    }
});

it('returns no undated events from any adapter', function (): void {
    expect($this->challenges->undated($this->mentee, 10))->toBe([]);
    expect($this->completions->undated($this->mentee, 10))->toBe([]);
    expect($this->submissions->undated($this->mentee, 10))->toBe([]);
    expect($this->paths->undated($this->mentee, 10))->toBe([]);
});
