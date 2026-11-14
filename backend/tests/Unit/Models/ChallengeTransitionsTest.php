<?php

use App\Models\Challenge;
use App\Models\LearningPath;
use App\Models\MentorshipRelationship;
use App\Models\PathModule;
use App\Models\User;
use App\Models\UserModuleCompletion;

uses()->group('models', 'progress');

const CHALLENGE_TRANSITION_MATRIX = [
    'template' => ['template'],
    'assigned' => ['assigned', 'in_progress', 'completed', 'canceled', 'abandoned'],
    'in_progress' => ['in_progress', 'completed', 'canceled', 'abandoned'],
    'completed' => ['completed'],
    'canceled' => ['canceled'],
    'abandoned' => ['abandoned', 'assigned', 'in_progress'],
];

it('allows exactly the expected status transitions for every status pair', function (): void {
    $challenge = new Challenge;

    foreach (Challenge::VALID_STATUSES as $from) {
        foreach (Challenge::VALID_STATUSES as $to) {
            $challenge->status = $from;
            expect(
                $challenge->canTransitionTo($to),
                "transition {$from} -> {$to}"
            )->toBe(in_array($to, CHALLENGE_TRANSITION_MATRIX[$from], true));
        }
    }

    $challenge->status = 'abandoned';
    expect($challenge->canTransitionTo('canceled'))->toBeFalse()
        ->and($challenge->canTransitionTo('completed'))->toBeFalse();
});

it('includes abandoned in the valid statuses', function (): void {
    expect(Challenge::VALID_STATUSES)->toContain('abandoned');
});

it('links a challenge to a path module and exposes the inverse relations', function (): void {
    $path = LearningPath::create(['title' => ['es' => 'Ruta'], 'is_active' => true]);
    $module = PathModule::create(['learning_path_id' => $path->id, 'title' => 'Módulo', 'order' => 1]);
    $challenge = Challenge::factory()->create();

    $challenge->path_module_id = $module->id;
    $challenge->save();

    expect($challenge->fresh()->pathModule)->toBeInstanceOf(PathModule::class)
        ->and($challenge->fresh()->pathModule->id)->toBe($module->id)
        ->and($module->challenges)->toHaveCount(1)
        ->and($module->challenges->first()->id)->toBe($challenge->id);
});

it('exposes module completions on users, modules and the completion model', function (): void {
    $path = LearningPath::create(['title' => ['es' => 'Ruta'], 'is_active' => true]);
    $module = PathModule::create(['learning_path_id' => $path->id, 'title' => 'Módulo', 'order' => 1]);
    $user = User::factory()->create();

    $completion = UserModuleCompletion::create([
        'user_id' => $user->id,
        'path_module_id' => $module->id,
        'completed_at' => now(),
    ]);

    expect($completion->fresh()->user)->toBeInstanceOf(User::class)
        ->and($completion->fresh()->user->id)->toBe($user->id)
        ->and($completion->fresh()->pathModule)->toBeInstanceOf(PathModule::class)
        ->and($completion->fresh()->pathModule->id)->toBe($module->id)
        ->and($completion->completed_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($user->moduleCompletions)->toHaveCount(1)
        ->and($module->completions)->toHaveCount(1)
        ->and($module->completions->first()->id)->toBe($completion->id);
});

it('ignores path_module_id on mass assignment', function (): void {
    $module = PathModule::create([
        'learning_path_id' => LearningPath::create(['title' => ['es' => 'Ruta'], 'is_active' => true])->id,
        'title' => 'Módulo',
        'order' => 1,
    ]);

    $relationship = MentorshipRelationship::factory()->create();
    $challenge = Challenge::create([
        'relationship_id' => $relationship->id,
        'mentor_id' => $relationship->mentor_id,
        'mentee_id' => $relationship->source_id,
        'title' => 'Reto',
        'status' => 'assigned',
        'path_module_id' => $module->id,
    ]);

    expect($challenge->fresh()->path_module_id)->toBeNull();

    $challenge->update(['path_module_id' => $module->id]);
    expect($challenge->fresh()->path_module_id)->toBeNull();
});
