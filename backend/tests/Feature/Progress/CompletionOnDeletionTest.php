<?php

use App\Models\Challenge;
use App\Models\LearningPath;
use App\Models\MentorshipRelationship;
use App\Models\PathModule;
use App\Models\User;
use App\Models\UserModuleCompletion;
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
});

function deletionModule(): PathModule
{
    $path = LearningPath::create([
        'title' => ['es' => 'Ruta'],
        'is_active' => true,
    ]);

    return PathModule::create([
        'learning_path_id' => $path->id,
        'title' => 'Módulo',
        'order' => 1,
    ]);
}

function linkedCompleted(MentorshipRelationship $relationship, PathModule $module): Challenge
{
    $challenge = Challenge::factory()->forRelationship($relationship)->create(['status' => 'completed']);
    $challenge->forceFill(['path_module_id' => $module->id])->save();

    return $challenge;
}

function grantRow(string $subjectId, string $moduleId): UserModuleCompletion
{
    return UserModuleCompletion::create([
        'user_id' => $subjectId,
        'path_module_id' => $moduleId,
        'completed_at' => now(),
    ]);
}

function completionEvents(string $moduleId, string $event)
{
    return Activity::query()
        ->where('log_name', 'module_completion')
        ->where('event', $event)
        ->where('properties->path_module_id', $moduleId)
        ->get();
}

it('revokes the module completion when the sustaining completed challenge is soft-deleted', function () {
    $module = deletionModule();
    $challenge = linkedCompleted($this->relationship, $module);
    grantRow($this->mentee->id, $module->id);

    $this->actingAs($this->mentor);
    $challenge->delete();

    expect(completionRowOf($this->mentee->id, $module->id))->toBeNull();

    $revocations = completionEvents($module->id, 'module_completion.revoked');
    expect($revocations)->toHaveCount(1);
    $properties = $revocations->first()->properties->toArray();
    expect($properties['triggered_by']['event'])->toBe('challenge.deleted')
        ->and($properties['triggered_by']['actor_id'])->toBe($this->mentor->id)
        ->and($properties['triggered_by']['challenge_id'])->toBe($challenge->id)
        ->and($properties['previous_completed_at'])->not->toBeNull();
});

it('re-grants the module completion when the deleted challenge is restored', function () {
    $module = deletionModule();
    $challenge = linkedCompleted($this->relationship, $module);
    grantRow($this->mentee->id, $module->id);
    $challenge->delete();
    expect(completionRowOf($this->mentee->id, $module->id))->toBeNull();

    $challenge->restore();

    expect(completionRowOf($this->mentee->id, $module->id))->not->toBeNull();
    $grants = completionEvents($module->id, 'module_completion.granted');
    expect($grants)->toHaveCount(1);
    expect($grants->first()->properties->toArray()['triggered_by']['event'])->toBe('challenge.restored');
});

it('revokes the module completion when the sustaining challenge is force-deleted', function () {
    $module = deletionModule();
    $challenge = linkedCompleted($this->relationship, $module);
    grantRow($this->mentee->id, $module->id);

    $challenge->forceDelete();

    expect(completionRowOf($this->mentee->id, $module->id))->toBeNull();
    $revocations = completionEvents($module->id, 'module_completion.revoked');
    expect($revocations)->toHaveCount(1);
    expect($revocations->first()->properties->toArray()['triggered_by']['event'])
        ->toBe('challenge.force_deleted');
});

it('does nothing when a non-linked challenge is deleted', function () {
    $module = deletionModule();
    grantRow($this->mentee->id, $module->id);
    $unlinked = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'assigned']);

    $unlinked->delete();

    expect(completionRowOf($this->mentee->id, $module->id))->not->toBeNull()
        ->and(completionEvents($module->id, 'module_completion.revoked'))->toHaveCount(0);
});

it('rolls back the delete and the recalculation together when the transaction fails', function () {
    $module = deletionModule();
    $challenge = linkedCompleted($this->relationship, $module);
    grantRow($this->mentee->id, $module->id);

    try {
        DB::transaction(function () use ($challenge) {
            $challenge->delete();

            throw new RuntimeException('post-delete failure');
        });
        $this->fail('expected transaction to fail');
    } catch (RuntimeException) {
    }

    expect(Challenge::withTrashed()->find($challenge->id)->deleted_at)->toBeNull()
        ->and(completionRowOf($this->mentee->id, $module->id))->not->toBeNull()
        ->and(completionEvents($module->id, 'module_completion.revoked'))->toHaveCount(0);
});

it('audits every link removal and completion revocation when a module is deleted', function () {
    $module = deletionModule();
    $first = linkedCompleted($this->relationship, $module);
    $second = linkedCompleted($this->relationship, $module);
    grantRow($this->mentee->id, $module->id);
    grantRow($this->mentor->id, $module->id);

    $this->actingAs($this->mentor);
    $moduleId = $module->id;
    $module->delete();

    expect(PathModule::find($moduleId))->toBeNull()
        ->and(UserModuleCompletion::query()->where('path_module_id', $moduleId)->count())->toBe(0)
        ->and($first->fresh()->path_module_id)->toBeNull()
        ->and($second->fresh()->path_module_id)->toBeNull();

    $linkAudits = Activity::query()
        ->where('event', 'challenge.module_link_changed')
        ->where('properties->reason', 'path_module_deleted')
        ->get();
    expect($linkAudits)->toHaveCount(2);
    foreach ([$first, $second] as $challenge) {
        $audit = $linkAudits->firstWhere('properties.challenge_id', $challenge->id);
        $properties = $audit->properties->toArray();
        expect($properties['before']['path_module_id'])->toBe($moduleId)
            ->and($properties['after']['path_module_id'])->toBeNull()
            ->and($properties['actor'])->toBe($this->mentor->id);
    }

    $revocations = completionEvents($moduleId, 'module_completion.revoked');
    expect($revocations)->toHaveCount(2);
    foreach ($revocations as $audit) {
        $properties = $audit->properties->toArray();
        expect($properties['triggered_by']['event'])->toBe('path_module.deleted')
            ->and($properties['previous_completed_at'])->not->toBeNull();
    }
});

it('audits every affected module when a learning path is deleted', function () {
    $path = LearningPath::create(['title' => ['es' => 'Ruta'], 'is_active' => true]);
    $moduleA = PathModule::create(['learning_path_id' => $path->id, 'title' => 'A', 'order' => 1]);
    $moduleB = PathModule::create(['learning_path_id' => $path->id, 'title' => 'B', 'order' => 2]);
    $challengeA = linkedCompleted($this->relationship, $moduleA);
    $challengeB = linkedCompleted($this->relationship, $moduleB);
    grantRow($this->mentee->id, $moduleA->id);
    grantRow($this->mentee->id, $moduleB->id);

    $path->delete();

    expect($challengeA->fresh()->path_module_id)->toBeNull()
        ->and($challengeB->fresh()->path_module_id)->toBeNull()
        ->and(UserModuleCompletion::query()->whereIn('path_module_id', [$moduleA->id, $moduleB->id])->count())->toBe(0);

    $linkAudits = Activity::query()
        ->where('event', 'challenge.module_link_changed')
        ->where('properties->reason', 'learning_path_deleted')
        ->get();
    expect($linkAudits)->toHaveCount(2);
    expect(completionEvents($moduleA->id, 'module_completion.revoked'))->toHaveCount(1)
        ->and(completionEvents($moduleB->id, 'module_completion.revoked'))->toHaveCount(1);
    expect($linkAudits->first()->properties->toArray()['triggered_by'] ?? null)->toBeNull();
});

function completionRowOf(string $subjectId, string $moduleId): ?UserModuleCompletion
{
    return UserModuleCompletion::query()
        ->where('user_id', $subjectId)
        ->where('path_module_id', $moduleId)
        ->first();
}
