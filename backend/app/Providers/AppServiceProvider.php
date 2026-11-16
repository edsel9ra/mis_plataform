<?php

namespace App\Providers;

use App\Events\SessionCanceled;
use App\Events\SessionRescheduled;
use App\Listeners\SessionCanceledListener;
use App\Listeners\SessionRescheduledListener;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\Company;
use App\Models\CompetencyAssessmentLink;
use App\Models\Employee;
use App\Models\FeedbackThread;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\LearningPath;
use App\Models\PathModule;
use App\Models\MediaAsset;
use App\Models\MentorshipRelationship;
use App\Models\NeurocognitiveAlert;
use App\Models\NeurocognitiveObservation;
use App\Models\Objective;
use App\Models\PersonalityAssessment;
use App\Models\Session;
use App\Models\SystemicMap;
use App\Models\User;
use App\Models\UserLearningProgress;
use App\Models\UserSkill;
use App\Observers\ChallengeObserver;
use App\Observers\CohortMemberObserver;
use App\Observers\EmployeeObserver;
use App\Observers\EvidenceSourceStateObserver;
use App\Observers\FamilyMemberObserver;
use App\Observers\LearningPathObserver;
use App\Observers\PathModuleObserver;
use App\Policies\ChallengePolicy;
use App\Policies\ChallengeSubmissionPolicy;
use App\Policies\CohortPolicy;
use App\Policies\FeedbackThreadPolicy;
use App\Policies\MediaAssetPolicy;
use App\Policies\NeurocognitiveAlertPolicy;
use App\Policies\NeurocognitiveObservationPolicy;
use App\Policies\ObjectivePolicy;
use App\Policies\RelationshipPolicy;
use App\Policies\SessionPolicy;
use App\Policies\SystemicMapPolicy;
use App\Services\Progress\History\ChallengeActivityHistoryAdapter;
use App\Services\Progress\History\ChallengeSubmissionHistoryAdapter;
use App\Services\Progress\History\CompetencyHistoryAdapter;
use App\Services\Progress\History\EvidenceDeletionHistoryAdapter;
use App\Services\Progress\History\LearningPathHistoryAdapter;
use App\Services\Progress\History\ModuleCompletionHistoryAdapter;
use App\Services\Progress\History\ObjectiveEvidenceHistoryAdapter;
use App\Services\Progress\History\ObjectiveHistoryAdapter;
use App\Services\Progress\History\ProgressHistoryService;
use App\Services\Progress\ProgressCacheObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // T015.6: the unified timeline merges every feed (plan §4 step 2).
        $this->app->bind(ProgressHistoryService::class, fn ($app) => new ProgressHistoryService([
            $app->make(ObjectiveHistoryAdapter::class),
            $app->make(ObjectiveEvidenceHistoryAdapter::class),
            $app->make(ChallengeActivityHistoryAdapter::class),
            $app->make(ModuleCompletionHistoryAdapter::class),
            $app->make(ChallengeSubmissionHistoryAdapter::class),
            $app->make(LearningPathHistoryAdapter::class),
            $app->make(CompetencyHistoryAdapter::class),
            $app->make(EvidenceDeletionHistoryAdapter::class),
        ]));
    }

    public function boot(): void
    {
        $uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

        Password::defaults(fn () => Password::min(8)->letters()->mixedCase()->numbers());

        Route::patterns([
            'id' => $uuid,
            'sessionId' => $uuid,
            'companyId' => $uuid,
            'memberId' => $uuid,
            'relationship' => $uuid,
            'challenge' => $uuid,
            'objective' => $uuid,
            'user' => $uuid,
            'mentor' => $uuid,
            'media' => $uuid,
            'window' => $uuid,
            'exception' => $uuid,
        ]);

        Event::listen(SessionRescheduled::class, SessionRescheduledListener::class);
        Event::listen(SessionCanceled::class, SessionCanceledListener::class);

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

        RateLimiter::for('auth-reset-password', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });

        ResetPassword::createUrlUsing(function (User $user, string $token) {
            $locale = $user->locale;
            $prefix = $locale
                && $locale !== config('app.fallback_locale')
                && in_array($locale, config('app.available_locales'), true)
                ? "/{$locale}"
                : '';

            return rtrim(config('app.frontend_url'), '/')
                .$prefix
                .'/reset-password?token='.$token
                .'&email='.urlencode($user->getEmailForPasswordReset());
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
        Gate::policy(Objective::class, ObjectivePolicy::class);
        Gate::policy(Session::class, SessionPolicy::class);
        Gate::policy(SystemicMap::class, SystemicMapPolicy::class);

        // Progress panel cache invalidation (RNF-5, T-084).
        Objective::observe(ProgressCacheObserver::class);
        Session::observe(ProgressCacheObserver::class);
        Challenge::observe(ProgressCacheObserver::class);
        UserLearningProgress::observe(ProgressCacheObserver::class);
        PersonalityAssessment::observe(ProgressCacheObserver::class);
        UserSkill::observe(ProgressCacheObserver::class);
        CompetencyAssessmentLink::observe(ProgressCacheObserver::class);
        PersonalityAssessment::observe(EvidenceSourceStateObserver::class);

        Challenge::observe(ChallengeObserver::class);
        PathModule::observe(PathModuleObserver::class);
        LearningPath::observe(LearningPathObserver::class);

        FamilyMember::observe(FamilyMemberObserver::class);
        CohortMember::observe(CohortMemberObserver::class);
        Employee::observe(EmployeeObserver::class);
    }
}
