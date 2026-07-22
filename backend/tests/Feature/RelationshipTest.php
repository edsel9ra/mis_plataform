<?php

use App\Models\MentorshipRelationship;
use App\Models\User;

uses()->group('relationships');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->mentor = User::factory()->mentor()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('lists relationships', function () {
    MentorshipRelationship::factory()->count(3)->create([
        'source_type' => 'user',
        'source_id' => $this->user->id,
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/relationships');

    $response->assertOk();
});

it('creates a relationship', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/relationships', [
            'type' => 'personal',
            'source_type' => 'user',
            'source_id' => $this->user->id,
            'mentor_id' => $this->mentor->id,
            'objectives' => 'Improve leadership skills',
        ]);

    $response->assertCreated()
        ->assertJsonFragment(['source_id' => $this->user->id]);
});

it('rejects non-mentor as mentor', function () {
    $nonMentor = User::factory()->create(['role' => 'mentee']);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/relationships', [
            'type' => 'personal',
            'source_type' => 'user',
            'source_id' => $this->user->id,
            'mentor_id' => $nonMentor->id,
        ]);

    $response->assertUnprocessable();
});

it('shows a relationship', function () {
    $relationship = MentorshipRelationship::factory()->create();

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/relationships/{$relationship->id}");

    $response->assertOk();
});

it('updates relationship status', function () {
    $relationship = MentorshipRelationship::factory()->pending()->create();

    $response = $this->withToken($this->token)
        ->putJson("/api/v1/relationships/{$relationship->id}/status", [
            'status' => 'active',
        ]);

    $response->assertOk()
        ->assertJsonFragment(['status' => 'active']);
});

it('deletes a relationship', function () {
    $relationship = MentorshipRelationship::factory()->create();

    $response = $this->withToken($this->token)
        ->deleteJson("/api/v1/relationships/{$relationship->id}");

    $response->assertOk();
    $this->assertSoftDeleted($relationship);
});
