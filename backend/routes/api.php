<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\MentorController;
use App\Http\Controllers\Api\MentorSkillController;
use App\Http\Controllers\Api\SkillTagController;
use App\Http\Controllers\Api\RelationshipController;
use App\Http\Controllers\Api\RelationshipContextController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\FamilyController;
use App\Http\Controllers\Api\CohortController;
use App\Http\Controllers\Api\PersonalityController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\LearningPathController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\EvaluationController;
use App\Http\Controllers\Api\MentorDashboardController;
use App\Http\Controllers\Api\MentorAlertController;
use App\Http\Controllers\Api\MentorChallengeController;
use App\Http\Controllers\Api\MentorFeedbackController;
use App\Http\Controllers\Api\MentorMediaController;
use App\Http\Controllers\Api\MentorObservationController;
use App\Http\Controllers\Api\MentorSystemicMapController;
use App\Http\Controllers\Api\MentorWorkspaceController;
use App\Http\Controllers\Api\MentorNeurocognitiveController;
use App\Http\Controllers\Api\MentorEmotionalCheckInController;
use App\Http\Controllers\Api\MediaAssetController;
use App\Http\Controllers\Api\MenteeChallengeController;
use App\Http\Controllers\Api\MenteeCheckInController;
use App\Http\Controllers\Api\MenteeEmotionalCheckInController;

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:auth-forgot-password');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/auth/{provider}/redirect', [AuthController::class, 'redirectToProvider']);
    Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware(['auth:sanctum', 'active']);

    // Public
    Route::get('/mentors', [MentorController::class, 'index']);
    Route::get('/mentors/{id}', [MentorController::class, 'show']);
    Route::get('/plans', [PlanController::class, 'index']);
    Route::get('/plans/{id}', [PlanController::class, 'show']);

    // Authenticated
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        // Profile
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::put('/profile/avatar', [ProfileController::class, 'updateAvatar']);
        Route::put('/profile/locale', [ProfileController::class, 'updateLocale']);

        // Mentor specialty catalog
        Route::get('/skill-tags', [SkillTagController::class, 'index']);

        // Personality
        Route::post('/personality/start-test', [PersonalityController::class, 'startTest']);
         Route::post('/personality/submit-answers', [PersonalityController::class, 'submitAnswers'])
             ->middleware('throttle:personality-score');
        Route::get('/personality/report', [PersonalityController::class, 'report']);
         Route::post('/personality/calculate', [PersonalityController::class, 'calculate'])
             ->middleware('throttle:personality-score');

        // Relationships
        Route::get('/relationship-contexts', [RelationshipContextController::class, 'index']);
        Route::post('/relationships', [RelationshipController::class, 'store']);
        Route::get('/relationships', [RelationshipController::class, 'index']);
        Route::get('/relationships/{id}', [RelationshipController::class, 'show']);
        Route::put('/relationships/{id}/status', [RelationshipController::class, 'updateStatus']);
        Route::delete('/relationships/{id}', [RelationshipController::class, 'destroy']);

        // Sessions
        Route::get('/sessions', [SessionController::class, 'index']);
        Route::post('/sessions', [SessionController::class, 'store']);
        Route::get('/sessions/{id}', [SessionController::class, 'show']);
        Route::put('/sessions/{id}', [SessionController::class, 'update']);
        Route::put('/sessions/{id}/status', [SessionController::class, 'updateStatus']);
        Route::delete('/sessions/{id}', [SessionController::class, 'destroy']);
        Route::post('/sessions/{id}/meet-link', [SessionController::class, 'generateMeetLink']);

        // Messages
        Route::get('/messages', [MessageController::class, 'index']);
        Route::post('/messages', [MessageController::class, 'store']);
        Route::get('/messages/{id}', [MessageController::class, 'show']);
        Route::put('/messages/{id}/read', [MessageController::class, 'markAsRead']);

        // Subscriptions
        Route::post('/subscriptions', [SubscriptionController::class, 'store']);
        Route::get('/subscriptions/active', [SubscriptionController::class, 'active']);
        Route::put('/subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel']);

        // Company
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::get('/companies/{id}', [CompanyController::class, 'show']);
        Route::put('/companies/{id}', [CompanyController::class, 'update']);

        // Employees
        Route::post('/companies/{companyId}/employees', [EmployeeController::class, 'invite']);
        Route::get('/companies/{companyId}/employees', [EmployeeController::class, 'index']);
        Route::put('/employees/{id}', [EmployeeController::class, 'update']);
        Route::post('/employees/{id}/activate', [EmployeeController::class, 'activate']);
        Route::delete('/employees/{id}', [EmployeeController::class, 'deactivate']);

        // Family
        Route::post('/family-groups', [FamilyController::class, 'store']);
        Route::get('/family-groups/{id}', [FamilyController::class, 'show']);
        Route::put('/family-groups/{id}', [FamilyController::class, 'update']);
        Route::post('/family-groups/{id}/members', [FamilyController::class, 'addMember']);
        Route::delete('/family-groups/{id}/members/{memberId}', [FamilyController::class, 'removeMember']);

        // Cohorts
        Route::post('/cohorts', [CohortController::class, 'store']);
        Route::get('/cohorts/{id}', [CohortController::class, 'show']);
        Route::put('/cohorts/{id}', [CohortController::class, 'update']);
        Route::post('/cohorts/{id}/members', [CohortController::class, 'addMember']);
        Route::delete('/cohorts/{id}/members/{memberId}', [CohortController::class, 'removeMember']);

        // Learning Paths
        Route::get('/learning-paths', [LearningPathController::class, 'index']);
        Route::get('/learning-paths/{id}', [LearningPathController::class, 'show']);
        Route::put('/learning-paths/{id}/progress', [LearningPathController::class, 'updateProgress']);

        // Evaluations
        Route::get('/evaluations', [EvaluationController::class, 'index']);
        Route::get('/evaluations/{id}', [EvaluationController::class, 'show']);
        Route::post('/evaluations/{id}/submit', [EvaluationController::class, 'submit']);
        Route::get('/mentor/evaluation-results', [EvaluationController::class, 'mentorResults']);

        // Mentee workspace activity. Every route resolves the relationship to
        // the authenticated personal user before reading or writing data.
        Route::get('/relationships/{relationship}/check-ins', [MenteeCheckInController::class, 'index']);
        Route::post('/relationships/{relationship}/check-ins', [MenteeCheckInController::class, 'store']);
        Route::get('/relationships/{relationship}/emotional-check-ins', [MenteeEmotionalCheckInController::class, 'index']);
        Route::post('/relationships/{relationship}/emotional-check-ins', [MenteeEmotionalCheckInController::class, 'store']);
        Route::get('/relationships/{relationship}/challenges', [MenteeChallengeController::class, 'index']);
        Route::get('/challenges/{challenge}', [MenteeChallengeController::class, 'show']);
        Route::get('/challenges/{challenge}/check-ins', [MenteeCheckInController::class, 'challengeIndex']);
        Route::post('/challenges/{challenge}/check-ins', [MenteeCheckInController::class, 'challengeStore']);
        Route::get('/challenges/{challenge}/submissions', [MenteeChallengeController::class, 'submissions']);
        Route::post('/challenges/{challenge}/submissions', [MenteeChallengeController::class, 'storeSubmission']);
        Route::get('/challenges/{challenge}/feedback', [MenteeChallengeController::class, 'feedback']);
        Route::get('/media/{media}', [MediaAssetController::class, 'show'])->name('media.show');

        Route::middleware('role:mentor')->prefix('mentor')->group(function () {
            Route::put('/skills', [MentorSkillController::class, 'updateOwn']);
            Route::get('/users', [MentorDashboardController::class, 'users']);
            Route::get('/users/{user}/personality', [MentorDashboardController::class, 'personality']);
            Route::get('/users/{user}/recommendations', [MentorDashboardController::class, 'recommendations']);
            Route::get('/users/{user}/profile', [MentorWorkspaceController::class, 'profile']);
            Route::get('/users/{user}/neurocognitive', [MentorNeurocognitiveController::class, 'show']);
            Route::put('/users/{user}/neurocognitive', [MentorObservationController::class, 'store']);
            Route::get('/users/{user}/workspace', [MentorWorkspaceController::class, 'show']);
            Route::get('/users/{user}/attention', [MentorWorkspaceController::class, 'show']);
            Route::get('/users/{user}/observations', [MentorObservationController::class, 'index']);
            Route::post('/users/{user}/observations', [MentorObservationController::class, 'store']);
            Route::get('/users/{user}/emotional-check-ins', [MentorEmotionalCheckInController::class, 'index']);
            Route::get('/users/{user}/challenges', [MentorChallengeController::class, 'index']);
            Route::post('/users/{user}/challenges', [MentorChallengeController::class, 'store']);
            Route::get('/users/{user}/systemic-map', [MentorSystemicMapController::class, 'show']);
            Route::put('/users/{user}/systemic-map', [MentorSystemicMapController::class, 'update']);
            Route::get('/users/{user}/alerts', [MentorAlertController::class, 'index']);
            Route::get('/challenges', [MentorChallengeController::class, 'catalog']);
            Route::post('/challenges', [MentorChallengeController::class, 'catalogStore']);
            Route::get('/challenges/{challenge}', [MentorChallengeController::class, 'show']);
            Route::put('/challenges/{challenge}', [MentorChallengeController::class, 'update']);
            Route::get('/challenges/{challenge}/feedback', [MentorFeedbackController::class, 'index']);
            Route::post('/challenges/{challenge}/feedback', [MentorFeedbackController::class, 'store']);
            Route::get('/feedback', [MentorFeedbackController::class, 'all']);
            Route::post('/feedback', [MentorFeedbackController::class, 'storeGeneral']);
            Route::post('/media', [MentorMediaController::class, 'store']);
            Route::get('/systemic-maps/{id}', [MentorSystemicMapController::class, 'showById']);
            Route::patch('/alerts/{id}/resolve', [MentorAlertController::class, 'resolve']);
        });

        // Resources
        Route::get('/resources', [ResourceController::class, 'index']);
        Route::get('/resources/{id}', [ResourceController::class, 'show']);

        // Certificates
        Route::get('/certificates', [CertificateController::class, 'index']);
        Route::get('/certificates/{id}', [CertificateController::class, 'show']);

        // Reviews
        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::get('/reviews/session/{sessionId}', [ReviewController::class, 'index']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        // Admin routes
        Route::middleware('role:super_admin|admin')->prefix('admin')->group(function () {
            // Users
            Route::get('/users', [AdminController::class, 'users']);
            Route::post('/users', [AdminController::class, 'userStore']);
            Route::get('/users/{id}', [AdminController::class, 'userShow']);
            Route::put('/users/{id}', [AdminController::class, 'userUpdate']);
            Route::delete('/users/{id}', [AdminController::class, 'userDestroy']);

            // Mentors
            Route::get('/mentors', [AdminController::class, 'mentors']);
            Route::post('/mentors', [AdminController::class, 'mentorStore']);
            Route::get('/mentors/{id}', [AdminController::class, 'mentorShow']);
            Route::put('/mentors/{id}', [AdminController::class, 'mentorUpdate']);
            Route::put('/mentors/{id}/skills', [MentorSkillController::class, 'updateForMentor']);
            Route::delete('/mentors/{id}', [AdminController::class, 'mentorDestroy']);

            // Mentor specialty catalog
            Route::get('/skill-tags', [SkillTagController::class, 'adminIndex']);
            Route::post('/skill-tags', [SkillTagController::class, 'store']);
            Route::put('/skill-tags/{id}', [SkillTagController::class, 'update']);
            Route::delete('/skill-tags/{id}', [SkillTagController::class, 'destroy']);

            // Sessions
            Route::get('/sessions', [AdminController::class, 'sessions']);
            Route::post('/sessions', [AdminController::class, 'sessionStore']);
            Route::get('/sessions/{id}', [AdminController::class, 'sessionShow']);
            Route::put('/sessions/{id}', [AdminController::class, 'sessionUpdate']);
            Route::put('/sessions/{id}/status', [AdminController::class, 'sessionUpdateStatus']);
            Route::delete('/sessions/{id}', [AdminController::class, 'sessionDestroy']);

            // Assessments
            Route::get('/assessments', [AdminController::class, 'assessments']);
            Route::post('/assessments', [AdminController::class, 'assessmentStore']);
            Route::get('/assessments/{id}', [AdminController::class, 'assessmentShow']);
            Route::put('/assessments/{id}', [AdminController::class, 'assessmentUpdate']);
            Route::delete('/assessments/{id}', [AdminController::class, 'assessmentDestroy']);

            // Configurable evaluation catalog
            Route::get('/evaluation-catalog', [AdminController::class, 'evaluationCatalog']);
            Route::post('/evaluation-catalog', [AdminController::class, 'evaluationCatalogStore']);
            Route::get('/evaluation-catalog/{id}', [AdminController::class, 'evaluationCatalogShow']);
            Route::put('/evaluation-catalog/{id}', [AdminController::class, 'evaluationCatalogUpdate']);
            Route::delete('/evaluation-catalog/{id}', [AdminController::class, 'evaluationCatalogDestroy']);
            Route::get('/evaluation-results', [AdminController::class, 'evaluationResults']);

            // Plans, Reports, Certificates
            Route::match(['get', 'post'], '/plans', [AdminController::class, 'plans']);
            Route::get('/reports', [AdminController::class, 'reports']);
        });

        // Recommendations (authenticated, not admin)
        Route::get('/personality/recommendations', [AdminController::class, 'recommendations']);
    });
});
