<?php

use App\Enums\ClientType;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

uses()->group('auth');

describe('registration', function () {
    beforeEach(function () {
        $this->plan = Plan::create([
            'client_type' => ClientType::Personal->value,
            'name' => ['es' => 'Personal'],
            'price_monthly' => 100,
            'price_yearly' => 1000,
            'is_active' => true,
        ]);
    });

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
            'plan_id' => $this->plan->id,
            'locale' => 'en',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'company_id'],
                'subscription' => ['id', 'plan_id', 'status', 'trial_ends_at'],
            ])
            ->assertJsonPath('subscription.status', 'trial')
            ->assertJsonMissingPath('subscription.payment_id')
            ->assertJsonMissingPath('token');

        $this->assertDatabaseHas('users', ['email' => 'john@example.com', 'locale' => 'en']);
        $this->assertDatabaseHas('subscriptions', ['plan_id' => $this->plan->id, 'status' => 'trial']);
    });

    it('fails with duplicate email', function () {
        User::factory()->create(['email' => 'dup@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane',
            'email' => 'dup@example.com',
            'password' => 'Password123!',
            'sex' => 'F',
            'birth_date' => '1990-01-01',
            'client_type' => ClientType::Personal->value,
            'plan_id' => $this->plan->id,
            'locale' => 'es',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('fails with invalid client type', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John',
            'email' => 'john@test.com',
            'password' => 'Password123!',
            'sex' => 'M',
            'birth_date' => '1990-01-01',
            'client_type' => 'invalid_type',
            'plan_id' => $this->plan->id,
            'locale' => 'es',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['client_type']);
    });

    it('rolls back the user and role when the plan does not match', function () {
        $familyPlan = Plan::create([
            'client_type' => ClientType::Familiar->value,
            'name' => ['es' => 'Familiar'],
            'price_monthly' => 200,
            'price_yearly' => 2000,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Atomic',
            'email' => 'atomic@example.com',
            'password' => 'Password123!',
            'sex' => 'N',
            'birth_date' => '1990-01-01',
            'client_type' => ClientType::Personal->value,
            'plan_id' => $familyPlan->id,
            'locale' => 'es',
        ])->assertUnprocessable()->assertJsonValidationErrors(['plan_id']);

        $this->assertDatabaseMissing('users', ['email' => 'atomic@example.com']);
        $this->assertDatabaseCount('subscriptions', 0);
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
            ->assertJsonStructure(['user'])
            ->assertJsonMissingPath('token');
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
    it('rejects inactive users and revokes all of their tokens', function () {
        $user = User::factory()->create(['is_active' => false]);
        $token = $user->createToken('test')->plainTextToken;
        $user->createToken('another-device');

        $this->withToken($token)
            ->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    });

    it('revokes tokens when a user is deactivated or deleted', function () {
        $deactivated = User::factory()->create();
        $deleted = User::factory()->create();
        $deactivated->createToken('test');
        $deleted->createToken('test');

        $deactivated->update(['is_active' => false]);
        $deleted->delete();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $deactivated->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $deleted->id]);
    });

    it('allows an inactive user to log out', function () {
        $user = User::factory()->create(['is_active' => false]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    });

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

        $response->assertOk()
            ->assertJsonStructure(['message'])
            ->assertJsonMissingPath('token');
    });
});
