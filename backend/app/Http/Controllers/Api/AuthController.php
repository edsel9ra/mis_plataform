<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateUserAction;
use App\Actions\ResolveOAuthUserAction;
use App\Actions\StartTrialSubscriptionAction;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

class AuthController
{
    private const OAUTH_PROVIDERS = ['google', 'linkedin', 'github'];

    public function __construct(
        private CreateUserAction $createUser,
        private StartTrialSubscriptionAction $startTrialSubscription,
        private ResolveOAuthUserAction $resolveOAuthUser,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        [$user, $subscription] = DB::transaction(function () use ($validated) {
            $user = $this->createUser->execute($validated);
            $subscription = $this->startTrialSubscription->execute($user, $validated['plan_id']);

            return [$user, $subscription];
        });

        $token = $this->createAuthToken($user);

        $user->load('personalityAssessment');

        return response()->json([
            'user' => new UserResource($user),
            'subscription' => new SubscriptionResource($subscription),
        ], 201)->withCookie($this->authCookie($token));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('auth.inactive')],
            ]);
        }

        $token = $this->createAuthToken($user);

        $user->load('personalityAssessment');

        return response()->json([
            'user' => new UserResource($user),
        ])->withCookie($this->authCookie($token));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => __('auth.logged_out')])
            ->withCookie($this->forgetAuthCookie());
    }

    public function redirectToProvider(Request $request, string $provider): JsonResponse
    {
        if (!$this->isAllowedOAuthProvider($provider)) {
            return response()->json(['error' => __('auth.invalid_provider')], 400);
        }

        $state = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $locale = $this->normalizeLocale($request->query('locale'));
        $url = Socialite::driver($this->socialiteDriver($provider))
            ->stateless()
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url])
            ->withCookie($this->oauthStateCookie($provider, "{$state}.{$locale}"));
    }

    public function handleProviderCallback(Request $request, string $provider): RedirectResponse
    {
        if (!$this->isAllowedOAuthProvider($provider)) {
            return $this->redirectToFrontendOAuth(['error' => __('auth.invalid_provider')]);
        }

        [$expectedState, $locale] = array_pad(explode('.', (string) $request->cookie($this->oauthStateCookieName($provider)), 2), 2, null);
        $locale = $this->normalizeLocale($locale);
        $actualState = (string) $request->query('state', '');

        if (!$expectedState || !$actualState || !hash_equals($expectedState, $actualState)) {
            return $this->oauthCallbackResponse(
                $this->redirectToFrontendOAuth(['error' => __('auth.invalid_oauth_state')], $locale),
                $provider,
            );
        }

        if ($request->filled('error')) {
            return $this->oauthCallbackResponse(
                $this->redirectToFrontendOAuth(['error' => __('auth.provider_error')], $locale),
                $provider,
            );
        }

        try {
            $socialUser = Socialite::driver($this->socialiteDriver($provider))->stateless()->user();
            $user = $this->resolveOAuthUser->execute(
                $provider,
                $socialUser,
                $this->providerEmailIsVerified($provider, $socialUser),
                $locale,
            );

            if (!$user->is_active) {
                return $this->oauthCallbackResponse(
                    $this->redirectToFrontendOAuth(['error' => __('auth.inactive')], $locale),
                    $provider,
                );
            }

            $token = $this->createAuthToken($user);
        } catch (DomainException $exception) {
            $translationKey = in_array($exception->getMessage(), [
                'auth.oauth_email_unverified',
                'auth.oauth_account_conflict',
            ], true) ? $exception->getMessage() : 'auth.provider_error';

            return $this->oauthCallbackResponse(
                $this->redirectToFrontendOAuth(['error' => __($translationKey)], $locale),
                $provider,
            );
        } catch (Throwable) {
            return $this->oauthCallbackResponse(
                $this->redirectToFrontendOAuth(['error' => __('auth.provider_error')], $locale),
                $provider,
            );
        }

        return $this->oauthCallbackResponse(
            $this->redirectToFrontendOAuth(['success' => '1'], $locale)->withCookie($this->authCookie($token)),
            $provider,
        );
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $this->createAuthToken($user);

        return response()->json(['message' => __('auth.refreshed')])
            ->withCookie($this->authCookie($token));
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        return response()->json([
            'status' => __($status),
        ]);
    }

    private function redirectToFrontendOAuth(array $params, ?string $locale = null): RedirectResponse
    {
        $frontendUrl = rtrim(config('services.oauth.redirect_base_url'), '/');
        $locale = $this->normalizeLocale($locale);
        $localePrefix = $locale === config('app.locale') ? '' : "/{$locale}";
        $fragment = http_build_query($params);

        return redirect()->away("{$frontendUrl}{$localePrefix}/oauth/callback#{$fragment}");
    }

    private function createAuthToken(User $user): string
    {
        $user->tokens()->delete();

        return $user->createToken('auth-token')->plainTextToken;
    }

    private function authCookie(string $token): Cookie
    {
        return cookie(
            config('sanctum.auth_cookie', 'mis_auth_token'),
            $token,
            (int) config('sanctum.expiration', 60 * 24 * 7),
            '/',
            null,
            $this->oauthCookieIsSecure(),
            true,
            false,
            config('session.same_site', 'lax'),
        );
    }

    private function forgetAuthCookie(): Cookie
    {
        return cookie()->forget(
            config('sanctum.auth_cookie', 'mis_auth_token'),
            '/',
            null,
        );
    }

    private function isAllowedOAuthProvider(string $provider): bool
    {
        return in_array($provider, self::OAUTH_PROVIDERS, true);
    }

    private function socialiteDriver(string $provider): string
    {
        return $provider === 'linkedin' ? 'linkedin-openid' : $provider;
    }

    private function oauthStateCookieName(string $provider): string
    {
        return "mis_oauth_state_{$provider}";
    }

    private function oauthStateCookie(string $provider, string $value): Cookie
    {
        return cookie(
            $this->oauthStateCookieName($provider),
            $value,
            10,
            "/api/v1/auth/{$provider}/callback",
            null,
            $this->oauthCookieIsSecure(),
            true,
            false,
            'lax',
        );
    }

    private function oauthCallbackResponse(RedirectResponse $response, string $provider): RedirectResponse
    {
        return $response->withCookie(cookie()->forget(
            $this->oauthStateCookieName($provider),
            "/api/v1/auth/{$provider}/callback",
            null,
        ));
    }

    private function oauthCookieIsSecure(): bool
    {
        return parse_url(config('services.oauth.redirect_base_url'), PHP_URL_SCHEME) === 'https'
            || (bool) (config('session.secure') ?? app()->isProduction());
    }

    private function normalizeLocale(mixed $locale): string
    {
        return is_string($locale) && in_array($locale, config('app.available_locales'), true)
            ? $locale
            : config('app.locale');
    }

    private function providerEmailIsVerified(string $provider, SocialiteUser $socialUser): bool
    {
        if ($provider === 'github') {
            // Socialite requests user:email and only maps GitHub's primary verified address.
            return trim((string) $socialUser->getEmail()) !== '';
        }

        $raw = method_exists($socialUser, 'getRaw') && is_array($socialUser->getRaw())
            ? $socialUser->getRaw()
            : [];
        $candidates = [
            $raw['email_verified'] ?? null,
            $raw['verified_email'] ?? null,
            $raw['emailAddressVerified'] ?? null,
            $socialUser->email_verified ?? null,
            $socialUser->verified_email ?? null,
        ];

        return collect($candidates)->contains(
            fn ($value) => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true,
        );
    }
}
