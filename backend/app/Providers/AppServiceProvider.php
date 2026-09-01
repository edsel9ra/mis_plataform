<?php

namespace App\Providers;

use App\Models\Cohort;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\Company;
use App\Models\FeedbackThread;
use App\Models\FamilyGroup;
use App\Models\MediaAsset;
use App\Models\MentorshipRelationship;
use App\Models\NeurocognitiveAlert;
use App\Models\NeurocognitiveObservation;
use App\Models\Session;
use App\Models\SystemicMap;
use App\Models\User;
use App\Policies\ChallengePolicy;
use App\Policies\ChallengeSubmissionPolicy;
use App\Policies\CohortPolicy;
use App\Policies\FeedbackThreadPolicy;
use App\Policies\MediaAssetPolicy;
use App\Policies\NeurocognitiveAlertPolicy;
use App\Policies\NeurocognitiveObservationPolicy;
use App\Policies\RelationshipPolicy;
use App\Policies\SessionPolicy;
use App\Policies\SystemicMapPolicy;
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
            'challenge' => Challenge::class,
            'challenge_submission' => ChallengeSubmission::class,
            'feedback_entry' => \App\Models\FeedbackEntry::class,
        ]);

        Gate::policy(Challenge::class, ChallengePolicy::class);
        Gate::policy(ChallengeSubmission::class, ChallengeSubmissionPolicy::class);
        Gate::policy(Cohort::class, CohortPolicy::class);
        Gate::policy(FeedbackThread::class, FeedbackThreadPolicy::class);
        Gate::policy(MediaAsset::class, MediaAssetPolicy::class);
        Gate::policy(MentorshipRelationship::class, RelationshipPolicy::class);
        Gate::policy(NeurocognitiveAlert::class, NeurocognitiveAlertPolicy::class);
        Gate::policy(NeurocognitiveObservation::class, NeurocognitiveObservationPolicy::class);
        Gate::policy(Session::class, SessionPolicy::class);
        Gate::policy(SystemicMap::class, SystemicMapPolicy::class);
    }
}
