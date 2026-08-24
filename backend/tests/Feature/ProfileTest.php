<?php

use App\Http\Resources\UserProfileResource;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('removes the previous avatar using its stored public URL', function () {
    Storage::fake('public');
    Storage::disk('public')->put('avatars/old.jpg', 'old');
    $this->user->update(['avatar' => Storage::disk('public')->url('avatars/old.jpg')]);

    $this->withToken($this->token)
        ->put('/api/v1/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('new.jpg', 10, 'image/jpeg'),
        ])
        ->assertOk();

    Storage::disk('public')->assertMissing('avatars/old.jpg');
    expect(Storage::disk('public')->allFiles('avatars'))->toHaveCount(1);
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

it('expires stale auth cookie on unauthorized profile request', function () {
    $cookieName = config('sanctum.auth_cookie', 'mis_auth_token');

    $this->withHeader('Cookie', "{$cookieName}=invalid-token")
        ->getJson('/api/v1/profile')
        ->assertUnauthorized()
        ->assertCookieExpired($cookieName);
});
