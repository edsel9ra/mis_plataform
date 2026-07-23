<?php

use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;

uses()->group('sessions');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('lists sessions', function () {
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $this->user->id,
    ]);
    Session::factory()->count(2)->create(['relationship_id' => $relationship->id]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/sessions');

    $response->assertOk();
});

it('creates a session', function () {
    $mentor = User::factory()->mentor()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'mentor_id' => $mentor->id,
        'source_type' => 'user',
        'source_id' => $this->user->id,
    ]);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/sessions', [
            'relationship_id' => $relationship->id,
            'session_type' => 'individual',
            'title' => 'Test Session',
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'duration_minutes' => 60,
        ]);

    $response->assertCreated()
        ->assertJsonFragment(['title' => 'Test Session']);
});

it('shows a session', function () {
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $this->user->id,
    ]);
    $session = Session::factory()->create(['relationship_id' => $relationship->id]);

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/sessions/{$session->id}");

    $response->assertOk();
});

it('updates session status', function () {
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $this->user->id,
    ]);
    $session = Session::factory()->create(['relationship_id' => $relationship->id]);

    $response = $this->withToken($this->token)
        ->putJson("/api/v1/sessions/{$session->id}/status", [
            'status' => 'completed',
        ]);

    $response->assertOk()
        ->assertJsonFragment(['status' => 'completed']);
});
