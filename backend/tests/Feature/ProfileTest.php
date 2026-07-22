<?php

use App\Http\Resources\UserProfileResource;
use App\Models\User;

uses()->group('profile');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('shows authenticated user profile', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/v1/profile');

    $response->assertOk()
        ->assertJsonFragment(['id' => $this->user->id]);
});

it('updates profile', function () {
    $response = $this->withToken($this->token)
        ->putJson('/api/v1/profile', [
            'name' => 'Updated Name',
            'locale' => 'en',
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'name' => 'Updated Name',
    ]);
});

it('updates locale', function () {
    $response = $this->withToken($this->token)
        ->putJson('/api/v1/profile/locale', [
            'locale' => 'en',
        ]);

    $response->assertOk()
        ->assertJson(['locale' => 'en']);
});

it('fails with invalid locale', function () {
    $response = $this->withToken($this->token)
        ->putJson('/api/v1/profile/locale', [
            'locale' => 'invalid',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['locale']);
});

it('requires authentication for profile', function () {
    $this->getJson('/api/v1/profile')->assertUnauthorized();
});
