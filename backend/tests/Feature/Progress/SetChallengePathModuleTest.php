<?php

use App\Actions\SetChallengePathModuleAction;
use App\Models\Challenge;
use App\Models\LearningPath;
use App\Models\MentorshipRelationship;
use App\Models\PathModule;
use App\Models\User;
use App\Models\UserLearningProgress;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    $this->action = app(SetChallengePathModuleAction::class);
});

function linkPath(array $attributes = []): LearningPath
{
    return LearningPath::create(array_merge([
        'title' => ['es' => 'Ruta'],
        'is_active' => true,
    ], $attributes));
}

function linkModuleFor(LearningPath $path): PathModule
{
    return PathModule::create([
        'learning_path_id' => $path->id,
        'title' => 'Módulo',
        'order' => 1,
    ]);
}

function visibleModule(User $subject, array $attributes = []): PathModule
{
    $path = linkPath($attributes);
    UserLearningProgress::create([
        'user_id' => $subject->id,
        'learning_path_id' => $path->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);

    return linkModuleFor($path);
}

function linkActivities(Challenge $challenge)
{
    return Activity::query()
        ->where('subject_type', (new Challenge)->getMorphClass())
        ->where('subject_id', $challenge->id)
        ->where('event', 'challenge.module_link_changed')
        ->get();
}

function setLink(Challenge $challenge, ?string $moduleId): void
{
    $challenge->forceFill(['path_module_id' => $moduleId])->save();
}

it('lets the titular mentee associate a challenge with a module on a consultable path and audits it', function () {
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create();
    $module = visibleModule($this->mentee);

    $this->action->execute($challenge, $this->mentee, $module->id, now());

    expect($challenge->fresh()->path_module_id)->toBe($module->id);

    $activities = linkActivities($challenge);
    expect($activities)->toHaveCount(1);
    $properties = $activities->first()->properties->toArray();
    expect($activities->first()->causer_id)->toBe($this->mentee->id)
        ->and($properties['challenge_id'])->toBe($challenge->id)
        ->and($properties['actor'])->toBe($this->mentee->id)
        ->and($properties['before']['path_module_id'])->toBeNull()
        ->and($properties['after']['path_module_id'])->toBe($module->id)
        ->and($properties['occurred_at'])->not->toBeNull()
        ->and($properties)->not->toHaveKeys(['title', 'description', 'instructions']);
});

it('lets the assigned mentor change the link between two consultable modules and audits before/after', function () {
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create();
    $previous = visibleModule($this->mentee);
    $target = visibleModule($this->mentee);
    setLink($challenge, $previous->id);

    $this->action->execute($challenge, $this->mentor, $target->id, now());

    expect($challenge->fresh()->path_module_id)->toBe($target->id);

    $activities = linkActivities($challenge);
    expect($activities)->toHaveCount(1);
    $properties = $activities->first()->properties->toArray();
    expect($properties['before']['path_module_id'])->toBe($previous->id)
        ->and($properties['after']['path_module_id'])->toBe($target->id);
});

it('lets an authorized actor remove the link when the current module path is consultable', function () {
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create();
    $current = visibleModule($this->mentee);
    setLink($challenge, $current->id);

    $this->action->execute($challenge, $this->mentor, null, now());

    expect($challenge->fresh()->path_module_id)->toBeNull();
    $properties = linkActivities($challenge)->first()->properties->toArray();
    expect($properties['before']['path_module_id'])->toBe($current->id)
        ->and($properties['after']['path_module_id'])->toBeNull();
});

it('denies actors who are neither titular nor assigned mentor without changing the link', function (callable $actorFactory) {
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create();
    $module = visibleModule($this->mentee);
    $actor = $actorFactory();

    expect(fn () => $this->action->execute($challenge, $actor, $module->id, now()))
        ->toThrow(ModelNotFoundException::class);

    expect($challenge->fresh()->path_module_id)->toBeNull()
        ->and(linkActivities($challenge))->toHaveCount(0);
})->with([
    'another mentee' => [function () {
        $other = User::withoutSyncingToSearch(fn () => User::factory()->create(['client_type' => 'personal']));
        $other->assignRole('mentee');

        return $other;
    }],
    'a mentor of another relationship' => [function () {
        $other = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create(['client_type' => 'personal']));
        $other->assignRole('mentor');

        return $other;
    }],
    'an unrelated user' => [fn () => User::withoutSyncingToSearch(fn () => User::factory()->create())],
]);

it('denies associating a module whose path the subject cannot consult', function () {
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create();
    $hidden = linkModuleFor(linkPath());

    expect(fn () => $this->action->execute($challenge, $this->mentee, $hidden->id, now()))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => $this->action->execute($challenge, $this->mentor, $hidden->id, now()))
        ->toThrow(ModelNotFoundException::class);

    expect($challenge->fresh()->path_module_id)->toBeNull()
        ->and(linkActivities($challenge))->toHaveCount(0);
});

it('denies a nonexistent module id', function () {
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create();

    expect(fn () => $this->action->execute($challenge, $this->mentee, \Illuminate\Support\Str::uuid()->toString(), now()))
        ->toThrow(ModelNotFoundException::class);

    expect($challenge->fresh()->path_module_id)->toBeNull()
        ->and(linkActivities($challenge))->toHaveCount(0);
});

it('denies removing the link when the current module path is no longer consultable', function () {
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create();
    $current = visibleModule($this->mentee);
    setLink($challenge, $current->id);
    UserLearningProgress::query()
        ->where('user_id', $this->mentee->id)
        ->where('learning_path_id', $current->learning_path_id)
        ->delete();

    expect(fn () => $this->action->execute($challenge, $this->mentee, null, now()))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => $this->action->execute($challenge, $this->mentor, null, now()))
        ->toThrow(ModelNotFoundException::class);

    expect($challenge->fresh()->path_module_id)->toBe($current->id)
        ->and(linkActivities($challenge))->toHaveCount(0);
});

it('denies changing the link when the current module path is no longer consultable', function () {
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create();
    $current = visibleModule($this->mentee);
    $target = visibleModule($this->mentee);
    setLink($challenge, $current->id);
    UserLearningProgress::query()
        ->where('user_id', $this->mentee->id)
        ->where('learning_path_id', $current->learning_path_id)
        ->delete();

    expect(fn () => $this->action->execute($challenge, $this->mentee, $target->id, now()))
        ->toThrow(ModelNotFoundException::class);

    expect($challenge->fresh()->path_module_id)->toBe($current->id)
        ->and(linkActivities($challenge))->toHaveCount(0);
});

it('denies linking a template', function () {
    $template = Challenge::factory()->create([
        'status' => 'template',
        'mentor_id' => $this->mentor->id,
        'relationship_id' => null,
        'mentee_id' => null,
    ]);
    $module = visibleModule($this->mentee);

    expect(fn () => $this->action->execute($template, $this->mentor, $module->id, now()))
        ->toThrow(ModelNotFoundException::class);

    expect($template->fresh()->path_module_id)->toBeNull()
        ->and(linkActivities($template))->toHaveCount(0);
});

it('denies the titular mentee when the relationship is not active', function () {
    $this->relationship->update(['status' => 'paused']);
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create();
    $module = visibleModule($this->mentee);

    expect(fn () => $this->action->execute($challenge, $this->mentee, $module->id, now()))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => $this->action->execute($challenge, $this->mentor, $module->id, now()))
        ->toThrow(ModelNotFoundException::class);

    expect($challenge->fresh()->path_module_id)->toBeNull()
        ->and(linkActivities($challenge))->toHaveCount(0);
});

it('treats setting the already-linked module as a no-op without audit', function () {
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create();
    $module = visibleModule($this->mentee);
    setLink($challenge, $module->id);

    $this->action->execute($challenge, $this->mentee, $module->id, now());

    expect($challenge->fresh()->path_module_id)->toBe($module->id)
        ->and(linkActivities($challenge))->toHaveCount(0);
});

it('never infers a link for untouched challenges', function () {
    $linked = Challenge::factory()->forRelationship($this->relationship)->create(['title' => 'Modulo uno']);
    $untouched = Challenge::factory()->forRelationship($this->relationship)->create(['title' => 'Modulo uno']);

    expect($untouched->fresh()->path_module_id)->toBeNull();

    $module = visibleModule($this->mentee);
    $this->action->execute($linked, $this->mentee, $module->id, now());

    expect($linked->fresh()->path_module_id)->toBe($module->id)
        ->and($untouched->fresh()->path_module_id)->toBeNull();
});
