<?php

use App\Models\MentorshipRelationship;
use App\Models\Plan;
use App\Models\Session;
use App\Models\Subscription;
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

    $response = $this->withToken($mentor->createToken('test')->plainTextToken)
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
    $mentor = User::factory()->mentor()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'mentor_id' => $mentor->id,
        'source_type' => 'user',
        'source_id' => $this->user->id,
    ]);
    $session = Session::factory()->create(['relationship_id' => $relationship->id]);

    $response = $this->withToken($mentor->createToken('test')->plainTextToken)
        ->putJson("/api/v1/sessions/{$session->id}/status", [
            'status' => 'completed',
        ]);

    $response->assertOk()
        ->assertJsonFragment(['status' => 'completed']);
});

it('requires an active relationship and a matching session type', function () {
    $mentor = User::factory()->mentor()->create();
    $inactive = MentorshipRelationship::factory()->pending()->create([
        'type' => 'personal',
        'mentor_id' => $mentor->id,
        'source_type' => 'user',
        'source_id' => $this->user->id,
    ]);

    $payload = [
        'relationship_id' => $inactive->id,
        'session_type' => 'individual',
        'title' => 'Inactive relationship',
        'scheduled_at' => now()->addDays(2)->toIso8601String(),
        'duration_minutes' => 60,
    ];

    $this->actingAs($mentor)->postJson('/api/v1/sessions', $payload)->assertUnprocessable();

    $activeMentor = User::factory()->mentor()->create();
    $active = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'mentor_id' => $activeMentor->id,
        'source_type' => 'user',
        'source_id' => $this->user->id,
    ]);
    $payload['relationship_id'] = $active->id;
    $payload['session_type'] = 'group';

    $this->actingAs($activeMentor)->postJson('/api/v1/sessions', $payload)->assertUnprocessable();
});

it('enforces the active plan monthly session limit', function () {
    $mentor = User::factory()->mentor()->create();
    $plan = Plan::create([
        'client_type' => 'personal',
        'name' => ['es' => 'Limited'],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_sessions_per_month' => 1,
        'is_active' => true,
    ]);
    Subscription::create([
        'subscriber_type' => 'user',
        'subscriber_id' => $this->user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'mentor_id' => $mentor->id,
        'source_type' => 'user',
        'source_id' => $this->user->id,
    ]);
    Session::factory()->create([
        'relationship_id' => $relationship->id,
        'session_type' => 'individual',
        'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($mentor)
        ->postJson('/api/v1/sessions', [
            'relationship_id' => $relationship->id,
            'session_type' => 'individual',
            'title' => 'Over limit',
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'duration_minutes' => 60,
        ])
        ->assertUnprocessable();
});
