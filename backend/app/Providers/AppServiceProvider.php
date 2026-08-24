<?php

namespace App\Providers;

use App\Models\Cohort;
use App\Models\Company;
use App\Models\FamilyGroup;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;
use App\Policies\CohortPolicy;
use App\Policies\RelationshipPolicy;
use App\Policies\SessionPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('auth-login', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });

        RateLimiter::for('auth-register', fn (Request $request) =>
            Limit::perMinute(5)->by($request->ip())
        );

        RateLimiter::for('auth-forgot-password', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });

        RateLimiter::for('personality-score', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(10)->by((string) $key);
        });

        Relation::morphMap([
            'user' => User::class,
            'family_group' => FamilyGroup::class,
            'cohort' => Cohort::class,
            'company' => Company::class,
        ]);

        Gate::policy(Cohort::class, CohortPolicy::class);
        Gate::policy(MentorshipRelationship::class, RelationshipPolicy::class);
        Gate::policy(Session::class, SessionPolicy::class);
    }
}
