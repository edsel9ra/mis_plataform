<?php

use App\Enums\ClientType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

uses()->group('auth');

describe('registration', function () {
    it('registers a new user', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'sex' => 'M',
            'birth_date' => '1990-01-01',
            'client_type' => ClientType::Personal->value,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    });

    it('fails with duplicate email', function () {
        User::factory()->create(['email' => 'dup@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane',
            'email' => 'dup@example.com',
            'password' => 'Password123!',
            'client_type' => ClientType::Personal->value,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('fails with invalid client type', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John',
            'email' => 'john@test.com',
            'password' => 'Password123!',
            'client_type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['client_type']);
    });
});

describe('login', function () {
    it('authenticates with valid credentials', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token']);
    });

    it('fails with invalid credentials', function () {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrong',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });
});

describe('authenticated actions', function () {
    it('logs out successfully', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    });

    it('refreshes token', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/refresh');

        $response->assertOk()->assertJsonStructure(['token']);
    });
});
