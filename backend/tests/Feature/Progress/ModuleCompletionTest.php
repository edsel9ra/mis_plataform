<?php

use App\Actions\SetChallengePathModuleAction;
use App\Actions\TransitionChallengeStatusAction;
use App\Models\Challenge;
use App\Models\LearningPath;
use App\Models\MentorshipRelationship;
use App\Models\PathModule;
use App\Models\User;
use App\Models\UserLearningProgress;
use App\Models\UserModuleCompletion;
use App\Services\Progress\ModuleCompletionService;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('mentor');
    Role::findOrCreate('mentee');

    $this->mentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
        'client_type' => 'personal',
    ]));
    $this->mentor->assignRole('mentor');
    $this->mentee = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'client_type' => 'personal',
    ]));
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
});

function completionPath(): LearningPath
{
    return LearningPath::create([
        'title' => ['es' => 'Ruta'],
        'is_active' => true,
    ]);
}

function completionModule(LearningPath $path): PathModule
{
    return PathModule::create([
        'learning_path_id' => $path->id,
        'title' => 'Módulo',
        'order' => 1,
    ]);
}

function consultableModule(User $subject): PathModule
{
    $path = completionPath();
    UserLearningProgress::create([
        'user_id' => $subject->id,
        'learning_path_id' => $path->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);

    return completionModule($path);
}

function linkTo(Challenge $challenge, PathModule $module): void
{
    $challenge->forceFill(['path_module_id' => $module->id])->save();
}

function completionRow(string $subjectId, string $moduleId): ?UserModuleCompletion
{
    return UserModuleCompletion::query()
        ->where('user_id', $subjectId)
        ->where('path_module_id', $moduleId)
        ->first();
}

function completionActivities(string $moduleId, ?string $event = null)
{
    $query = Activity::query()
        ->where('log_name', 'module_completion')
        ->where('properties->path_module_id', $moduleId);

    if ($event !== null) {
        $query->where('event', $event);
    }

    return $query->get();
}

it('grants the module completion only when every eligible linked challenge is completed', function () {
    $module = completionModule(completionPath());
    $first = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'assigned']);
    $second = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'assigned']);
    linkTo($first, $module);
    linkTo($second, $module);

    $this->transition->execute($first, $this->mentor, 'completed', now());
    expect(completionRow($this->mentee->id, $module->id))->toBeNull();

    $this->transition->execute($second, $this->mentor, 'completed', now());

    $row = completionRow($this->mentee->id, $module->id);
    expect($row)->not->toBeNull()->and($row->completed_at)->not->toBeNull();

    $grants = completionActivities($module->id, 'module_completion.granted');
    expect($grants)->toHaveCount(1);
    $activity = $grants->first();
    $properties = $activity->properties->toArray();
    expect($activity->causer_id)->toBeNull()
        ->and($properties['actor'])->toBe('system')
        ->and($properties['user_id'])->toBe($this->mentee->id)
        ->and($properties['path_module_id'])->toBe($module->id)
        ->and($properties['result'])->toBe('completed')
        ->and($properties['triggered_by']['actor_id'])->toBe($this->mentor->id)
        ->and($properties['triggered_by']['event'])->toBe('challenge.status_transition')
        ->and($properties['triggered_by']['challenge_id'])->toBe($second->id)
        ->and($properties['occurred_at'])->not->toBeNull()
        ->and($properties)->not->toHaveKeys(['title', 'description']);
});

it('ignores excluded statuses so a completed plus abandoned set grants', function () {
    $module = consultableModule($this->mentee);
    $done = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'completed']);
    $abandoned = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'abandoned']);
    linkTo($abandoned, $module);

    $this->setLink->execute($done, $this->mentee, $module->id, now());

    expect(completionRow($this->mentee->id, $module->id))->not->toBeNull()
        ->and(completionActivities($module->id, 'module_completion.granted'))->toHaveCount(1);
});

it('revokes the completion when an abandoned challenge is reactivated', function () {
    $module = completionModule(completionPath());
    $done = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'completed']);
    $abandoned = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'abandoned']);
    linkTo($done, $module);
    linkTo($abandoned, $module);
    UserModuleCompletion::create([
        'user_id' => $this->mentee->id,
        'path_module_id' => $module->id,
        'completed_at' => now()->subDays(3),
    ]);

    $this->transition->execute($abandoned, $this->mentor, 'in_progress', now());

    expect(completionRow($this->mentee->id, $module->id))->toBeNull();

    $revocations = completionActivities($module->id, 'module_completion.revoked');
    expect($revocations)->toHaveCount(1);
    $properties = $revocations->first()->properties->toArray();
    expect($properties['result'])->toBe('not_completed')
        ->and($properties['previous_completed_at'])->not->toBeNull()
        ->and($properties['triggered_by']['event'])->toBe('challenge.status_transition')
        ->and($properties['triggered_by']['actor_id'])->toBe($this->mentor->id);
});

it('blocks the completion when a linked challenge has an unknown status', function () {
    $module = completionModule(completionPath());
    $first = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'assigned']);
    $weird = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'assigned']);
    linkTo($first, $module);
    linkTo($weird, $module);
    DB::table('challenges')->where('id', $weird->id)->update(['status' => 'weird']);

    $this->transition->execute($first, $this->mentor, 'in_progress', now());

    expect(completionRow($this->mentee->id, $module->id))->toBeNull()
        ->and(completionActivities($module->id))->toHaveCount(0);
});

it('revokes the completion when the last completed challenge is unlinked', function () {
    $module = consultableModule($this->mentee);
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'completed']);
    linkTo($challenge, $module);
    UserModuleCompletion::create([
        'user_id' => $this->mentee->id,
        'path_module_id' => $module->id,
        'completed_at' => now(),
    ]);

    $this->setLink->execute($challenge, $this->mentee, null, now());

    expect(completionRow($this->mentee->id, $module->id))->toBeNull();
    $properties = completionActivities($module->id, 'module_completion.revoked')->first()->properties->toArray();
    expect($properties['result'])->toBe('no_data')
        ->and($properties['triggered_by']['event'])->toBe('challenge.module_link_changed');
});

it('recalculates both modules when a challenge moves between them', function () {
    $origin = consultableModule($this->mentee);
    $target = consultableModule($this->mentee);
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'completed']);
    linkTo($challenge, $origin);
    UserModuleCompletion::create([
        'user_id' => $this->mentee->id,
        'path_module_id' => $origin->id,
        'completed_at' => now(),
    ]);

    $this->setLink->execute($challenge, $this->mentee, $target->id, now());

    expect(completionRow($this->mentee->id, $origin->id))->toBeNull()
        ->and(completionRow($this->mentee->id, $target->id))->not->toBeNull()
        ->and(completionActivities($origin->id, 'module_completion.revoked'))->toHaveCount(1)
        ->and(completionActivities($target->id, 'module_completion.granted'))->toHaveCount(1);
});

it('is idempotent on no-op transitions and repeated same-link writes', function () {
    $module = completionModule(completionPath());
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'completed']);
    linkTo($challenge, $module);

    $this->transition->execute($challenge, $this->mentor, 'completed', now());
    expect(completionRow($this->mentee->id, $module->id))->toBeNull()
        ->and(completionActivities($module->id))->toHaveCount(0);
});

it('ignores challenges of a different mentee on the same module', function () {
    $module = completionModule(completionPath());
    $otherMentee = User::withoutSyncingToSearch(fn () => User::factory()->create(['client_type' => 'personal']));
    $otherRelationship = MentorshipRelationship::create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $otherMentee->id,
        'mentor_id' => $this->mentor->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    $foreign = Challenge::factory()->forRelationship($otherRelationship)->create(['status' => 'completed']);
    linkTo($foreign, $module);

    $own = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'assigned']);
    linkTo($own, $module);

    $this->transition->execute($own, $this->mentor, 'in_progress', now());

    expect(completionRow($this->mentee->id, $module->id))->toBeNull()
        ->and(completionRow($otherMentee->id, $module->id))->toBeNull();
});

it('rolls back the status change and the completion together when recalculation throws', function () {
    $module = completionModule(completionPath());
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'assigned']);
    linkTo($challenge, $module);

    app()->instance(ModuleCompletionService::class, new class(app(\App\Services\Progress\Calculators\ChallengeModuleCalculator::class)) extends ModuleCompletionService {
        public function recalculate(string $subjectId, string $pathModuleId, ?User $triggeredBy, string $triggerEvent, ?string $challengeId, \Carbon\CarbonInterface $now): void
        {
            parent::recalculate($subjectId, $pathModuleId, $triggeredBy, $triggerEvent, $challengeId, $now);

            throw new RuntimeException('recalculation blew up');
        }
    });

    try {
        app(TransitionChallengeStatusAction::class)->execute($challenge, $this->mentor, 'completed', now());
        $this->fail('expected recalculation exception');
    } catch (RuntimeException) {
    }

    expect($challenge->fresh()->status)->toBe('assigned')
        ->and(completionRow($this->mentee->id, $module->id))->toBeNull()
        ->and(completionActivities($module->id))->toHaveCount(0);
});

it('refuses to recalculate outside a transaction', function () {
    // RefreshDatabase wraps the test in a transaction; leave it so the
    // service sees a real transactionLevel() of 0.
    DB::rollBack();
    $module = completionModule(completionPath());

    try {
        expect(fn () => app(ModuleCompletionService::class)->recalculate(
            $this->mentee->id,
            $module->id,
            $this->mentor,
            'test.trigger',
            null,
            now(),
        ))->toThrow(LogicException::class);
    } finally {
        $module->delete();
        $module->learningPath->forceDelete();
    }
});
