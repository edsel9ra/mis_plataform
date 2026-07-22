<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

uses()->group('admin');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create(['role' => 'super_admin']);
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

it('denies non-admin users', function () {
    $user = User::factory()->create(['role' => 'mentee']);
    $user->assignRole('mentee');
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/admin/users')
        ->assertForbidden();
});
