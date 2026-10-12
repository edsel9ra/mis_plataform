<?php

use App\Models\User;
use App\Models\Plan;
use Database\Seeders\RolePermissionSeeder;

uses()->group('admin');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->superAdmin()->create();
    $this->admin->assignRole('super_admin');
    $this->token = $this->admin->createToken('test')->plainTextToken;
});

it('lists users', function () {
    User::factory()->count(3)->create();

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/admin/users');

    $response->assertOk();
});

it('filters users by client type', function () {
    User::factory()->create(['client_type' => 'personal']);
    User::factory()->create(['client_type' => 'empresa']);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/admin/users?client_type=personal');

    $response->assertOk();
});

it('creates platform admins without client type', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/admin/users', [
            'name' => 'Platform',
            'email' => 'platform@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'locale' => 'es',
        ]);

    $response->assertCreated();

    $this->assertDatabaseHas('users', [
        'email' => 'platform@example.com',
        'role' => 'admin',
        'client_type' => null,
    ]);
});

it('requires client type for non platform users', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/admin/users', [
            'name' => 'Client',
            'email' => 'client@example.com',
            'password' => 'password123',
            'role' => 'mentee',
            'locale' => 'es',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['client_type']);
});

it('lists mentors', function () {
    User::factory()->mentor()->count(2)->create();

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/admin/mentors');

    $response->assertOk();
});

it('shows reports', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/v1/admin/reports');

    $response->assertOk()
        ->assertJsonStructure(['total_users', 'active_relationships']);
});

it('lists sessions', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/v1/admin/sessions');

    $response->assertOk();
});

it('lists assessments', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/v1/admin/assessments');

    $response->assertOk();
});

it('creates plans with stable codes and entitlements', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/admin/plans', [
            'code' => 'admin_personal_plus',
            'client_type' => 'personal',
            'name' => [
                'es' => 'Personal Plus',
                'en' => 'Personal Plus',
                'pt' => 'Pessoal Plus',
            ],
            'price_monthly' => 20,
            'price_yearly' => 200,
            'max_sessions_per_month' => 8,
            'max_members' => 1,
            'max_mentors' => 1,
            'entitlements' => [
                'mentor_workspace' => true,
                'ocean' => true,
            ],
            'is_active' => false,
        ]);

    $response->assertCreated()
        ->assertJsonPath('code', 'admin_personal_plus')
        ->assertJsonPath('entitlements.ocean', true)
        ->assertJsonPath('is_active', false);
    expect(Plan::where('code', 'admin_personal_plus')->exists())->toBeTrue();
});

it('denies non-admin users', function () {
    $user = User::factory()->create(['role' => 'mentee']);
    $user->assignRole('mentee');
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/admin/users')
        ->assertForbidden();
});
