<?php

use App\Actions\ResolveOAuthUserAction;
use App\Models\OAuthAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Two\User as SocialiteUser;

function oauthSocialUser(array $attributes, array $raw = []): SocialiteUser
{
    return (new SocialiteUser())->setRaw($raw)->map($attributes);
}

beforeEach(function () {
    config(['scout.driver' => 'collection']);
});

it('resolves an exact oauth account without requiring provider email again', function () {
    $user = User::factory()->create();
    OAuthAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-123',
    ]);

    $resolved = app(ResolveOAuthUserAction::class)->execute(
        'google',
        oauthSocialUser(['id' => 'google-123', 'email' => null]),
        false,
        'en',
    );

    expect($resolved->is($user))->toBeTrue();
});

it('migrates an exact legacy provider account', function () {
    $user = User::factory()->create([
        'provider' => 'github',
        'provider_id' => 'legacy-123',
    ]);

    $resolved = app(ResolveOAuthUserAction::class)->execute(
        'github',
        oauthSocialUser(['id' => 'legacy-123', 'email' => null]),
        false,
        'es',
    );

    expect($resolved->is($user))->toBeTrue();
    $this->assertDatabaseHas('oauth_accounts', [
        'user_id' => $user->id,
        'provider' => 'github',
        'provider_user_id' => 'legacy-123',
    ]);
});

it('links a verified provider email without overwriting profile or security fields', function () {
    $password = Hash::make('ExistingPassword123!');
    $user = User::factory()->admin()->create([
        'email' => 'person@example.com',
        'password' => $password,
        'avatar' => 'https://cdn.example.com/custom-avatar.png',
    ]);

    $resolved = app(ResolveOAuthUserAction::class)->execute(
        'google',
        oauthSocialUser([
            'id' => 'google-456',
            'name' => 'Provider Name',
            'email' => 'PERSON@example.com',
            'avatar' => 'https://provider.example.com/avatar.png',
        ]),
        true,
        'en',
    );

    $resolved->refresh();

    expect($resolved->id)->toBe($user->id)
        ->and($resolved->role)->toBe('admin')
        ->and($resolved->client_type)->toBeNull()
        ->and($resolved->password)->toBe($password)
        ->and($resolved->avatar)->toBe('https://cdn.example.com/custom-avatar.png');
});

it('rejects automatic linking when the provider email is not verified', function () {
    User::factory()->create(['email' => 'person@example.com']);

    app(ResolveOAuthUserAction::class)->execute(
        'google',
        oauthSocialUser(['id' => 'google-789', 'email' => 'person@example.com']),
        false,
        'es',
    );
})->throws(DomainException::class, 'auth.oauth_email_unverified');

it('rejects a second identity for the same user and provider', function () {
    $user = User::factory()->create(['email' => 'person@example.com']);
    OAuthAccount::create([
        'user_id' => $user->id,
        'provider' => 'github',
        'provider_user_id' => 'github-first',
    ]);

    app(ResolveOAuthUserAction::class)->execute(
        'github',
        oauthSocialUser(['id' => 'github-second', 'email' => 'person@example.com']),
        true,
        'es',
    );
})->throws(DomainException::class, 'auth.oauth_account_conflict');
