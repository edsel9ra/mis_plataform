<?php

use App\Models\User;
use App\Models\FamilyGroup;
use App\Models\Plan;

uses()->group('domain-writes');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('creates a company and links its administrator', function () {
    $this->user->update(['client_type' => 'empresa']);

    $response = $this->withToken($this->token)->postJson('/api/v1/companies', [
        'legal_name' => 'Atomic Company',
        'industry' => 'technology',
        'size' => 'startup',
    ])->assertCreated()->assertJsonPath('legal_name', 'Atomic Company');

    $companyId = $response->json('id');
    $this->assertDatabaseHas('companies', ['id' => $companyId, 'admin_id' => $this->user->id]);
    $this->assertDatabaseHas('users', ['id' => $this->user->id, 'company_id' => $companyId]);
});

it('creates a family with its head member', function () {
    $response = $this->withToken($this->token)->postJson('/api/v1/family-groups', [
        'family_name' => 'Atomic Family',
    ])->assertCreated()->assertJsonPath('family_name', 'Atomic Family');

    $this->assertDatabaseHas('family_members', [
        'family_group_id' => $response->json('id'),
        'user_id' => $this->user->id,
        'relationship' => 'head',
    ]);
});

it('creates a cohort with its creator as an active member', function () {
    $response = $this->withToken($this->token)->postJson('/api/v1/cohorts', [
        'name' => 'Atomic Cohort',
        'max_members' => 10,
    ])->assertCreated()->assertJsonPath('name', 'Atomic Cohort');

    $this->assertDatabaseHas('cohort_members', [
        'cohort_id' => $response->json('id'),
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);
});

it('does not let a family owner self-assign a paid plan', function () {
    $family = FamilyGroup::create([
        'head_user_id' => $this->user->id,
        'family_name' => 'Protected Family',
    ]);
    $plan = Plan::create([
        'client_type' => 'familiar',
        'name' => ['es' => 'Family plan'],
        'price_monthly' => 1,
        'price_yearly' => 10,
        'is_active' => true,
    ]);

    $this->withToken($this->token)
        ->putJson("/api/v1/family-groups/{$family->id}", ['plan_id' => $plan->id])
        ->assertUnprocessable();

    $this->assertDatabaseHas('family_groups', [
        'id' => $family->id,
        'plan_id' => null,
    ]);
});
