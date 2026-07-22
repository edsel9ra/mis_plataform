<?php

use App\Actions\CreateSessionAction;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;

uses()->group('actions');

it('creates a session with attendees', function () {
    $mentor = User::factory()->mentor()->create();
    $mentee = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'mentor_id' => $mentor->id,
    ]);
    $action = app(CreateSessionAction::class);

    $session = $action->execute([
        'relationship_id' => $relationship->id,
        'session_type' => 'individual',
        'title' => 'Test Session',
        'scheduled_at' => now()->addDays(2)->toIso8601String(),
        'duration_minutes' => 60,
        'attendee_ids' => [$mentee->id],
    ]);

    expect($session)->toBeInstanceOf(Session::class)
        ->and($session->attendees)->toHaveCount(2);
});

it('creates session without extra attendees', function () {
    $mentor = User::factory()->mentor()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'mentor_id' => $mentor->id,
    ]);
    $action = app(CreateSessionAction::class);

    $session = $action->execute([
        'relationship_id' => $relationship->id,
        'session_type' => 'individual',
        'title' => 'Minimal Session',
        'scheduled_at' => now()->addDays(2)->toIso8601String(),
        'duration_minutes' => 30,
    ]);

    expect($session->status)->toBe('scheduled');
});
