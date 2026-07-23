<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateUserAction;
use App\Enums\ClientType;
use App\Enums\UserRole;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController
{
    public function __construct(
        private CreateUserAction $createUser,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->createUser->execute($request->validated());

        $token = $user->createToken('auth-token')->plainTextToken;

        $user->load('personalityAssessment');

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
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

        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        $user->load('personalityAssessment');

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('auth.logged_out')]);
    }

    public function redirectToProvider(string $provider): JsonResponse
    {
        $providers = ['google', 'linkedin', 'github'];

        if (!in_array($provider, $providers)) {
            return response()->json(['error' => __('auth.invalid_provider')], 400);
        }

        $url = Socialite::driver($provider)->stateless()->redirect()->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    public function handleProviderCallback(string $provider): JsonResponse|RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return $this->redirectToFrontendOAuth(['error' => __('auth.provider_error')]);
        }

        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if (!$user) {
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'avatar' => $socialUser->getAvatar(),
                ]);
            } else {
                $user = User::create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                    'email' => $socialUser->getEmail(),
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'avatar' => $socialUser->getAvatar(),
                    'client_type' => ClientType::Personal->value,
                    'role' => UserRole::Mentee->value,
                ]);
                $user->syncApplicationRole(UserRole::Mentee->value);
            }
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->redirectToFrontendOAuth(['token' => $token]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json(['token' => $token]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return response()->json([
            'status' => __($status),
        ]);
    }

    private function redirectToFrontendOAuth(array $params): RedirectResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $fragment = http_build_query($params);

        return redirect()->away("{$frontendUrl}/oauth/callback#{$fragment}");
    }
}
