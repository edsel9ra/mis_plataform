<?php

use App\Enums\ObjectiveScope;
use App\Enums\ObjectiveStatus;
use App\Models\Objective;

uses()->group('progress');

it('exposes translatable scope labels', function () {
    app()->setLocale('es');

    expect(ObjectiveScope::Individual->label())->toBe('Individual')
        ->and(ObjectiveScope::Collective->label())->toBe('Colectivo')
        ->and(ObjectiveScope::Individual->value)->toBe('individual')
        ->and(ObjectiveScope::Collective->value)->toBe('collective');
});

it('validates status transitions and rejects self-transitions', function () {
    expect(ObjectiveStatus::NotStarted->canTransitionTo(ObjectiveStatus::InProgress))->toBeTrue()
        ->and(ObjectiveStatus::NotStarted->canTransitionTo(ObjectiveStatus::Achieved))->toBeTrue()
        ->and(ObjectiveStatus::NotStarted->canTransitionTo(ObjectiveStatus::NotStarted))->toBeFalse()
        ->and(ObjectiveStatus::Achieved->canTransitionTo(ObjectiveStatus::InProgress))->toBeTrue()
        ->and(ObjectiveStatus::Abandoned->canTransitionTo(ObjectiveStatus::Achieved))->toBeFalse()
        ->and(ObjectiveStatus::InProgress->canTransitionTo('unknown'))->toBeFalse();
});

it('derives overdue but never marks an achieved objective as overdue', function () {
    $now = now();

    $pending = Objective::factory()->create(['due_at' => $now->copy()->subDay()]);
    $achieved = Objective::factory()->achieved()->create(['due_at' => $now->copy()->subDay()]);

    expect($pending->isOverdue($now))->toBeTrue()
        ->and($achieved->isOverdue($now))->toBeFalse();
});

it('records status changes in the activity log with old and new values', function () {
    $objective = Objective::factory()->create(['status' => 'not_started']);
    $objective->update(['status' => 'in_progress']);

    $activity = $objective->activities()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['old']['status'])->toBe('not_started')
        ->and($activity->properties['attributes']['status'])->toBe('in_progress');
});
