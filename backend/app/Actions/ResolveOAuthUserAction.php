<?php

namespace App\Actions;

use App\Enums\ClientType;
use App\Enums\UserRole;
use App\Models\OAuthAccount;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class ResolveOAuthUserAction
{
    public function execute(
        string $provider,
        SocialiteUser $socialUser,
        bool $emailVerified,
        string $locale,
    ): User {
        $providerUserId = trim((string) $socialUser->getId());

        if ($providerUserId === '') {
            throw new DomainException('auth.oauth_account_conflict');
        }

        return DB::transaction(function () use ($provider, $socialUser, $emailVerified, $locale, $providerUserId) {
            $account = OAuthAccount::query()
                ->where('provider', $provider)
                ->where('provider_user_id', $providerUserId)
                ->lockForUpdate()
                ->first();

            if ($account) {
                return $this->lockedUser($account->user_id);
            }

            $legacyUsers = User::query()
                ->where('provider', $provider)
                ->where('provider_id', $providerUserId)
                ->lockForUpdate()
                ->get();

            if ($legacyUsers->count() > 1) {
                throw new DomainException('auth.oauth_account_conflict');
            }

            if ($legacyUser = $legacyUsers->first()) {
                $this->attachAccount($legacyUser, $provider, $providerUserId);

                return $legacyUser;
            }

            $email = mb_strtolower(trim((string) $socialUser->getEmail()));

            if (!$emailVerified || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new DomainException('auth.oauth_email_unverified');
            }

            $users = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->get();

            if ($users->count() > 1) {
                throw new DomainException('auth.oauth_account_conflict');
            }

            $user = $users->first();

            if (!$user) {
                $name = trim((string) ($socialUser->getName() ?: $socialUser->getNickname()));
                $name = $name !== '' ? $name : strstr($email, '@', true);

                $user = User::create([
                    'name' => mb_substr($name, 0, 100),
                    'email' => $email,
                    'email_verified_at' => now(),
                    'provider' => $provider,
                    'provider_id' => $providerUserId,
                    'avatar' => $socialUser->getAvatar(),
                    'client_type' => ClientType::Personal->value,
                    'role' => UserRole::Mentee->value,
                    'locale' => $locale,
                    'is_active' => true,
                ]);
                $user->syncApplicationRole();
            } elseif ($user->provider === null && $user->provider_id === null) {
                $user->update([
                    'provider' => $provider,
                    'provider_id' => $providerUserId,
                ]);
            }

            $this->attachAccount($user, $provider, $providerUserId);

            return $user;
        }, 3);
    }

    private function lockedUser(string $userId): User
    {
        $user = User::query()->lockForUpdate()->find($userId);

        if (!$user) {
            throw new DomainException('auth.oauth_account_conflict');
        }

        return $user;
    }

    private function attachAccount(User $user, string $provider, string $providerUserId): void
    {
        $existing = OAuthAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->lockForUpdate()
            ->first();

        if ($existing && $existing->provider_user_id !== $providerUserId) {
            throw new DomainException('auth.oauth_account_conflict');
        }

        if (!$existing) {
            OAuthAccount::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
            ]);
        }
    }
}
