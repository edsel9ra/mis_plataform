<?php

use App\Models\MentorshipRelationship;
use App\Models\Cohort;
use App\Models\Company;
use App\Models\FamilyGroup;
use App\Models\Plan;
use App\Models\Subscription;
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

it('enforces the mentor limit of the source plan', function () {
    $plan = Plan::create([
        'client_type' => 'personal',
        'name' => ['es' => 'Personal limitado'],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_mentors' => 1,
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

    $this->withToken($this->token)
        ->postJson('/api/v1/relationships', [
            'type' => 'personal',
            'source_type' => 'user',
            'source_id' => $this->user->id,
            'mentor_id' => $this->mentor->id,
        ])
        ->assertCreated();

    $secondMentor = User::factory()->mentor()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/relationships', [
            'type' => 'personal',
            'source_type' => 'user',
            'source_id' => $this->user->id,
            'mentor_id' => $secondMentor->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mentor_id']);
});

it('lists only relationship contexts managed by the current user', function () {
    $plan = Plan::create([
        'client_type' => 'familiar',
        'name' => ['es' => 'Familia'],
        'price_monthly' => 10,
        'price_yearly' => 100,
    ]);
    $family = FamilyGroup::create([
        'head_user_id' => $this->user->id,
        'family_name' => 'Familia propia',
        'plan_id' => $plan->id,
    ]);
    $cohort = Cohort::create([
        'created_by' => $this->user->id,
        'name' => 'Cohorte propia',
    ]);
    $company = Company::create([
        'admin_id' => $this->user->id,
        'legal_name' => 'Empresa propia',
    ]);
    $other = User::factory()->create();
    $otherFamily = FamilyGroup::create([
        'head_user_id' => $other->id,
        'family_name' => 'Familia ajena',
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/relationship-contexts');

    $response->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonFragment([
            'source_type' => 'family_group',
            'source_id' => $family->id,
            'type' => 'familiar',
            'name' => 'Familia propia',
        ])
        ->assertJsonFragment(['source_id' => $cohort->id])
        ->assertJsonFragment(['source_id' => $company->id])
        ->assertJsonMissing(['source_id' => $otherFamily->id])
        ->assertJsonPath('data.1.plan.name', 'Familia');
});

it('returns an existing relationship for mentor-compatible contexts', function () {
    $relationship = MentorshipRelationship::factory()->pending()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $this->user->id,
        'mentor_id' => $this->mentor->id,
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/relationship-contexts?mentor_id={$this->mentor->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.relationship.id', $relationship->id)
        ->assertJsonPath('data.0.relationship.status', 'pending');
});

it('requires authentication to list relationship contexts', function () {
    $this->getJson('/api/v1/relationship-contexts')->assertUnauthorized();
});

it('prevents mentors from requesting themselves', function () {
    $token = $this->mentor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/relationships', [
            'type' => 'personal',
            'source_type' => 'user',
            'source_id' => $this->mentor->id,
            'mentor_id' => $this->mentor->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mentor_id']);
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

it('rejects an inactive mentor', function () {
    $inactiveMentor = User::factory()->mentor()->create(['is_active' => false]);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/relationships', [
            'type' => 'personal',
            'source_type' => 'user',
            'source_id' => $this->user->id,
            'mentor_id' => $inactiveMentor->id,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['mentor_id']);
});

it('shows a relationship', function () {
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $this->user->id,
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/relationships/{$relationship->id}");

    $response->assertOk();
});

it('updates relationship status', function () {
    $relationship = MentorshipRelationship::factory()->pending()->create([
        'source_type' => 'user',
        'source_id' => $this->user->id,
    ]);

    $response = $this->withToken($this->token)
        ->putJson("/api/v1/relationships/{$relationship->id}/status", [
            'status' => 'active',
        ]);

    $response->assertOk()
        ->assertJsonFragment(['status' => 'active']);
});

it('deletes a relationship', function () {
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $this->user->id,
    ]);

    $response = $this->withToken($this->token)
        ->deleteJson("/api/v1/relationships/{$relationship->id}");

    $response->assertOk();
    $this->assertSoftDeleted($relationship);
});
