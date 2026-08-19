<?php

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse;

uses()->group('auth', 'oauth');

beforeEach(function () {
    config([
        'scout.driver' => 'collection',
        'services.oauth.redirect_base_url' => 'http://app.mis-project.local',
        'session.secure' => false,
    ]);
});

it('allowlists providers and creates an explicit state cookie', function () {
    $state = null;
    $provider = Mockery::mock();
    $provider->shouldReceive('stateless')->once()->andReturnSelf();
    $provider->shouldReceive('with')->once()->withArgs(function (array $parameters) use (&$state) {
        $state = $parameters['state'] ?? null;

        return is_string($state) && strlen($state) >= 40;
    })->andReturnSelf();
    $provider->shouldReceive('redirect')->once()->andReturnUsing(function () use (&$state) {
        return new RedirectResponse('https://provider.example.com/oauth?state='.urlencode($state));
    });
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $response = $this->getJson('/api/v1/auth/google/redirect?locale=en');

    $response->assertOk()->assertJsonPath('url', 'https://provider.example.com/oauth?state='.urlencode($state));
    $cookie = $response->getCookie('mis_oauth_state_google', false);

    expect($cookie)->not->toBeNull()
        ->and($cookie->getValue())->toBe("{$state}.en")
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getDomain())->toBeNull();

    $this->getJson('/api/v1/auth/facebook/redirect')->assertBadRequest();
});

it('rejects callback state before exchanging the authorization code and expires the cookie', function () {
    Socialite::shouldReceive('driver')->never();

    $response = $this->withUnencryptedCookies(['mis_oauth_state_google' => 'expected.en'])
        ->get('/api/v1/auth/google/callback?state=different&code=secret');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/en/oauth/callback#error=');
    $response->assertCookieExpired('mis_oauth_state_google');
});

it('creates an oauth account and returns only a host-only auth cookie', function () {
    $socialUser = (new SocialiteUser())->setRaw([
        'sub' => 'google-user-123',
        'email' => 'oauth@example.com',
        'email_verified' => true,
    ])->map([
        'id' => 'google-user-123',
        'name' => 'OAuth User',
        'email' => 'oauth@example.com',
        'avatar' => 'https://provider.example.com/avatar.png',
    ]);
    $provider = Mockery::mock();
    $provider->shouldReceive('stateless')->once()->andReturnSelf();
    $provider->shouldReceive('user')->once()->andReturn($socialUser);
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $response = $this->withUnencryptedCookies(['mis_oauth_state_google' => 'valid-state.en'])
        ->get('/api/v1/auth/google/callback?state=valid-state&code=secret');

    $response->assertRedirect('http://app.mis-project.local/en/oauth/callback#success=1')
        ->assertCookieExpired('mis_oauth_state_google');
    $authCookie = $response->getCookie('mis_auth_token', false);

    expect($authCookie)->not->toBeNull()
        ->and($authCookie->isHttpOnly())->toBeTrue()
        ->and($authCookie->getDomain())->toBeNull();
    expect($response->headers->get('Location'))->not->toContain('token');
    $this->assertDatabaseHas('oauth_accounts', [
        'provider' => 'google',
        'provider_user_id' => 'google-user-123',
    ]);
});

it('rejects an unverifiable provider email without exposing exception details', function () {
    $socialUser = (new SocialiteUser())->setRaw([
        'sub' => 'google-user-456',
        'email' => 'oauth@example.com',
        'email_verified' => false,
    ])->map([
        'id' => 'google-user-456',
        'name' => 'OAuth User',
        'email' => 'oauth@example.com',
    ]);
    $provider = Mockery::mock();
    $provider->shouldReceive('stateless')->once()->andReturnSelf();
    $provider->shouldReceive('user')->once()->andReturn($socialUser);
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $response = $this->withUnencryptedCookies(['mis_oauth_state_google' => 'valid-state.es'])
        ->get('/api/v1/auth/google/callback?state=valid-state&code=secret');

    $response->assertRedirect()->assertCookieExpired('mis_oauth_state_google');
    expect(urldecode($response->headers->get('Location')))
        ->toContain(__('auth.oauth_email_unverified'))
        ->not->toContain('DomainException');
    $this->assertDatabaseCount('oauth_accounts', 0);
});
