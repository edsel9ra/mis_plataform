<?php

use App\Actions\CreateRelationshipAction;
use App\Models\MentorshipRelationship;
use App\Models\User;

uses()->group('actions');

it('creates a relationship with a valid mentor', function () {
    $mentor = User::factory()->mentor()->create();
    $action = app(CreateRelationshipAction::class);

    $relationship = $action->execute([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => User::factory()->create()->id,
        'mentor_id' => $mentor->id,
        'objectives' => 'Growth',
    ]);

    expect($relationship)->toBeInstanceOf(MentorshipRelationship::class)
        ->and($relationship->status)->toBe('pending');
});

it('rejects non-mentor users', function () {
    $nonMentor = User::factory()->create(['role' => 'mentee']);
    $action = app(CreateRelationshipAction::class);

    $action->execute([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => User::factory()->create()->id,
        'mentor_id' => $nonMentor->id,
    ]);
})->throws(\Illuminate\Validation\ValidationException::class);

it('prevents duplicate active relationships', function () {
    $mentor = User::factory()->mentor()->create();
    $sourceId = User::factory()->create()->id;

    MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $sourceId,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);

    $action = app(CreateRelationshipAction::class);

    $action->execute([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $sourceId,
        'mentor_id' => $mentor->id,
    ]);
})->throws(\Illuminate\Validation\ValidationException::class);
