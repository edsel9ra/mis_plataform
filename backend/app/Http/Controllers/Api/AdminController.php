<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateSessionAction;
use App\Enums\UserRole;
use App\Http\Requests\Admin\StoreAdminAssessmentRequest;
use App\Http\Requests\Admin\StoreAdminMentorRequest;
use App\Http\Requests\Admin\StoreAdminPlanRequest;
use App\Http\Requests\Admin\StoreAdminSessionRequest;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminAssessmentRequest;
use App\Http\Requests\Admin\UpdateAdminMentorRequest;
use App\Http\Requests\Admin\UpdateAdminSessionRequest;
use App\Http\Requests\Admin\UpdateAdminSessionStatusRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Http\Resources\AssessmentResource;
use App\Http\Resources\AssessmentResultResource;
use App\Http\Resources\AdminMentorResource;
use App\Http\Resources\MentorResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\RecommendationMentorResource;
use App\Http\Resources\RecommendationSessionResource;
use App\Http\Resources\SessionResource;
use App\Http\Resources\UserResource;
use App\Models\AssessmentResult;
use App\Models\MentorshipRelationship;
use App\Models\Assessment as LearningAssessment;
use App\Models\PersonalityAssessment;
use App\Models\Plan;
use App\Models\Session;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController
{
    // ──────────────────────────────────────────────
    // Users CRUD
    // ──────────────────────────────────────────────

    public function users(Request $request): JsonResponse
    {
        $query = User::query();
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        if ($request->filled('client_type')) {
            $query->byClientType($request->client_type);
        }

        if ($request->filled('role')) {
            $query->byRole($request->role);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $users = $query->withCount('mentorRelationships')
            ->with(['personalityAssessment', 'company'])
            ->latest()
            ->paginate($perPage);

        return response()->json($users);
    }

    public function userShow(string $id): JsonResponse
    {
        $user = User::with(['personalityAssessment', 'company', 'skills.skillTag'])
            ->withCount('mentorRelationships')
            ->findOrFail($id);

        return response()->json(new UserResource($user));
    }

    public function userStore(StoreAdminUserRequest $request): JsonResponse
    {
        $validated = $this->normalizeUserPayload($request->validated());

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);
        $user->syncApplicationRole();

        return response()->json(new UserResource($user), 201);
    }

    public function userUpdate(UpdateAdminUserRequest $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $this->normalizeUserPayload($request->validated());

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        if (array_key_exists('role', $validated)) {
            $user->syncApplicationRole($validated['role']);
        }

        return response()->json(new UserResource($user));
    }

    public function userDestroy(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        abort_if($user->isSuperAdmin() && !$request->user()?->isSuperAdmin(), 403);

        $user->delete();

        return response()->json(['message' => __('admin.user_deleted')]);
    }

    // ──────────────────────────────────────────────
    // Mentors CRUD
    // ──────────────────────────────────────────────

    public function mentors(Request $request): JsonResponse
    {
        $query = User::byRole('mentor');
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        if ($request->filled('client_type')) {
            $query->where('client_type', $request->client_type);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $mentors = $query
            ->with(['skills.skillTag', 'personalityAssessment', 'mentorRelationships' => function ($q) {
                $q->whereIn('status', ['active', 'completed']);
            }])
            ->withCount(['mentorRelationships as active_mentees_count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->paginate($perPage);

        return response()->json(AdminMentorResource::collection($mentors));
    }

    public function mentorShow(string $id): JsonResponse
    {
        $mentor = User::byRole('mentor')
            ->with(['skills.skillTag', 'personalityAssessment', 'mentorRelationships' => function ($q) {
                $q->whereIn('status', ['active', 'completed']);
            }])
            ->withCount(['mentorRelationships as active_mentees_count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->findOrFail($id);

        return response()->json(new AdminMentorResource($mentor));
    }

    public function mentorStore(StoreAdminMentorRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['password'] = Hash::make($validated['password']);
        $validated['role'] = 'mentor';

        $user = User::create($validated);
        $user->syncApplicationRole('mentor');

        return response()->json(new AdminMentorResource($user->load('skills.skillTag')), 201);
    }

    public function mentorUpdate(UpdateAdminMentorRequest $request, string $id): JsonResponse
    {
        $mentor = User::byRole('mentor')->findOrFail($id);

        $validated = $request->validated();

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $mentor->update($validated);

        return response()->json(new AdminMentorResource($mentor->load('skills.skillTag')));
    }

    public function mentorDestroy(string $id): JsonResponse
    {
        $mentor = User::byRole('mentor')->findOrFail($id);
        $mentor->delete();

        return response()->json(['message' => __('admin.mentor_deleted')]);
    }

    // ──────────────────────────────────────────────
    // Sessions CRUD
    // ──────────────────────────────────────────────

    public function sessions(Request $request): JsonResponse
    {
        $query = Session::with(['relationship.mentor', 'relationship.source', 'attendees.user']);
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        $sessions = $query->latest('scheduled_at')->paginate($perPage);

        return response()->json($sessions);
    }

    public function sessionShow(string $id): JsonResponse
    {
        $session = Session::with(['relationship.mentor', 'relationship.source', 'attendees.user'])
            ->findOrFail($id);

        return response()->json($session);
    }

    public function sessionStore(StoreAdminSessionRequest $request, CreateSessionAction $createSession): JsonResponse
    {
        $validated = $request->validated();

        $status = $validated['status'] ?? 'scheduled';
        unset($validated['status']);

        $session = $createSession->execute($validated);

        if ($status !== 'scheduled') {
            $session->update(['status' => $status]);
        }

        return response()->json(new SessionResource($session), 201);
    }

    public function sessionUpdate(UpdateAdminSessionRequest $request, string $id): JsonResponse
    {
        $session = Session::findOrFail($id);

        $validated = $request->validated();

        if (array_key_exists('relationship_id', $validated) || array_key_exists('session_type', $validated)) {
            $relationship = MentorshipRelationship::findOrFail(
                $validated['relationship_id'] ?? $session->relationship_id,
            );
            abort_unless($relationship->status === 'active', 422, __('sessions.relationship_inactive'));

            $expectedType = match ($relationship->type) {
                'personal' => 'individual',
                'familiar' => 'family',
                'grupal' => 'group',
                'empresa' => 'corporate',
                default => null,
            };
            abort_unless(
                $expectedType !== null && ($validated['session_type'] ?? $session->session_type) === $expectedType,
                422,
                __('sessions.type_mismatch'),
            );
        }

        $session->update($validated);

        return response()->json(new SessionResource($session));
    }

    public function sessionUpdateStatus(UpdateAdminSessionStatusRequest $request, string $id): JsonResponse
    {
        $session = Session::findOrFail($id);

        $validated = $request->validated();

        $session->update($validated);

        return response()->json(new SessionResource($session));
    }

    public function sessionDestroy(string $id): JsonResponse
    {
        $session = Session::findOrFail($id);
        $session->delete();

        return response()->json(['message' => __('admin.session_deleted')]);
    }

    // ──────────────────────────────────────────────
    // Personality Assessments CRUD
    // ──────────────────────────────────────────────

    public function assessments(Request $request): JsonResponse
    {
        $query = PersonalityAssessment::with('user');
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        if ($request->filled('completed')) {
            $query->whereNotNull('completed_at');
        }

        $assessments = $query->latest()->paginate($perPage);

        return response()->json($assessments);
    }

    public function assessmentShow(string $id): JsonResponse
    {
        $assessment = PersonalityAssessment::with('user')->findOrFail($id);

        return response()->json($assessment);
    }

    public function assessmentStore(StoreAdminAssessmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $assessment = PersonalityAssessment::create($validated);

        return response()->json($assessment, 201);
    }

    public function assessmentUpdate(UpdateAdminAssessmentRequest $request, string $id): JsonResponse
    {
        $assessment = PersonalityAssessment::findOrFail($id);

        $validated = $request->validated();

        $assessment->update($validated);

        return response()->json($assessment);
    }

    public function assessmentDestroy(string $id): JsonResponse
    {
        $assessment = PersonalityAssessment::findOrFail($id);
        $assessment->delete();

        return response()->json(['message' => __('admin.assessment_deleted')]);
    }

    // ──────────────────────────────────────────────
    // Evaluation catalog
    // ──────────────────────────────────────────────

    public function evaluationCatalog(Request $request): JsonResponse
    {
        $query = LearningAssessment::withCount('results')->latest();
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where(function ($q) use ($term) {
                $q->where('slug', 'ilike', "%{$term}%")
                    ->orWhere('category', 'ilike', "%{$term}%")
                    ->orWhere('title->es', 'ilike', "%{$term}%")
                    ->orWhere('title->en', 'ilike', "%{$term}%");
            });
        }

        if ($request->filled('client_type')) {
            $query->whereJsonContains('client_types', $request->client_type);
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return response()->json($query->paginate($perPage));
    }

    public function evaluationCatalogStore(Request $request): JsonResponse
    {
        $assessment = LearningAssessment::create($this->validatedEvaluationCatalogPayload($request));

        return response()->json($assessment, 201);
    }

    public function evaluationCatalogShow(string $id): JsonResponse
    {
        return response()->json(LearningAssessment::withCount('results')->findOrFail($id));
    }

    public function evaluationCatalogUpdate(Request $request, string $id): JsonResponse
    {
        $assessment = LearningAssessment::findOrFail($id);
        $assessment->update($this->validatedEvaluationCatalogPayload($request, $assessment->id));

        return response()->json($assessment);
    }

    public function evaluationCatalogDestroy(string $id): JsonResponse
    {
        $assessment = LearningAssessment::findOrFail($id);
        $assessment->delete();

        return response()->json(['message' => __('admin.evaluation_deleted')]);
    }

    public function evaluationResults(Request $request): JsonResponse
    {
        $query = AssessmentResult::with(['user', 'assessment'])->latest('completed_at');
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        if ($request->filled('assessment_id')) {
            $query->where('assessment_id', $request->assessment_id);
        }

        if ($request->filled('client_type')) {
            $query->whereHas('user', fn ($q) => $q->where('client_type', $request->client_type));
        }

        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where(function ($q) use ($term) {
                $q->whereHas('user', function ($userQuery) use ($term) {
                    $userQuery->where('name', 'ilike', "%{$term}%")
                        ->orWhere('last_name', 'ilike', "%{$term}%")
                        ->orWhere('email', 'ilike', "%{$term}%");
                })->orWhereHas('assessment', function ($assessmentQuery) use ($term) {
                    $assessmentQuery->where('slug', 'ilike', "%{$term}%")
                        ->orWhere('title->es', 'ilike', "%{$term}%")
                        ->orWhere('title->en', 'ilike', "%{$term}%");
                });
            });
        }

        $results = $query->paginate($perPage);

        return response()->json([
            'data' => AssessmentResultResource::collection($results->getCollection())->resolve($request),
            'current_page' => $results->currentPage(),
            'last_page' => $results->lastPage(),
            'per_page' => $results->perPage(),
            'total' => $results->total(),
        ]);
    }

    // ──────────────────────────────────────────────
    // Plans
    // ──────────────────────────────────────────────

    public function plans(StoreAdminPlanRequest $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
            $plans = Plan::withCount('subscriptions')->paginate($perPage);

            return response()->json($plans);
        }

        if ($request->isMethod('post')) {
            $validated = $request->validated();

            $plan = Plan::create($validated);

            return response()->json(new PlanResource($plan), 201);
        }

        return response()->json(['error' => 'Method not allowed'], 405);
    }

    // ──────────────────────────────────────────────
    // Reports
    // ──────────────────────────────────────────────

    public function reports(): JsonResponse
    {
        $totalUsers = User::count();
        $usersByType = User::whereNotNull('client_type')
            ->selectRaw('client_type, count(*) as total')
            ->groupBy('client_type')
            ->get();
        $usersByRole = User::selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->get();

        $activeRelationships = MentorshipRelationship::where('status', 'active')->count();
        $totalSessions = Session::count();
        $completedSessions = Session::where('status', 'completed')->count();

        $revenueByPlan = \App\Models\Subscription::selectRaw('plan_id, count(*) as total')
            ->whereIn('status', ['active', 'trial'])
            ->groupBy('plan_id')
            ->with('plan')
            ->get();

        return response()->json([
            'total_users' => $totalUsers,
            'users_by_type' => $usersByType,
            'users_by_role' => $usersByRole,
            'active_relationships' => $activeRelationships,
            'total_sessions' => $totalSessions,
            'completed_sessions' => $completedSessions,
            'revenue_by_plan' => $revenueByPlan,
        ]);
    }

    private function normalizeUserPayload(array $validated): array
    {
        if (isset($validated['role']) && in_array($validated['role'], [UserRole::SuperAdmin->value, UserRole::Admin->value], true)) {
            $validated['client_type'] = null;
        }

        return $validated;
    }

    private function validatedEvaluationCatalogPayload(Request $request, ?string $assessmentId = null): array
    {
        return $request->validate([
            'module_id' => ['nullable', 'exists:path_modules,id'],
            'slug' => ['required', 'string', 'max:120', 'unique:assessments,slug' . ($assessmentId ? ',' . $assessmentId : '')],
            'title' => ['required', 'array'],
            'title.es' => ['required', 'string', 'max:200'],
            'title.en' => ['nullable', 'string', 'max:200'],
            'title.pt' => ['nullable', 'string', 'max:200'],
            'short_name' => ['nullable', 'array'],
            'description' => ['nullable', 'array'],
            'category' => ['nullable', 'string', 'max:80'],
            'client_types' => ['nullable', 'array'],
            'client_types.*' => ['string', 'in:personal,familiar,grupal,empresa'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.id' => ['nullable'],
            'questions.*.text' => ['required', 'string'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:6'],
            'questions.*.options.*' => ['required', 'string'],
            'questions.*.dimension' => ['nullable', 'string', 'max:120'],
            'questions.*.reverse_score' => ['nullable', 'boolean'],
            'passing_score' => ['required', 'integer', 'min:0', 'max:100'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'is_active' => ['boolean'],
            'metadata' => ['nullable', 'array'],
        ]);
    }

    // ──────────────────────────────────────────────
    // Personality-based Recommendations
    // ──────────────────────────────────────────────

    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();

        $assessment = $user->personalityAssessment;

        if (!$assessment || !$assessment->completed_at) {
            return response()->json([
                'message' => __('recommendations.test_required'),
                'recommended_mentors' => [],
                'recommended_sessions' => [],
                'recommended_evaluations' => [],
            ]);
        }

        $ocean = $assessment->getOceanScores();

        // Find compatible mentors with personality assessment data
        $recommendedMentors = User::byRole('mentor')
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->when($user->client_type, fn ($q) => $q->where('client_type', $user->client_type))
            ->whereHas('personalityAssessment', function ($q) {
                $q->whereNotNull('completed_at');
            })
            ->with(['personalityAssessment', 'skills.skillTag'])
            ->withCount(['mentorRelationships as active_mentees_count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->get()
            ->map(function ($mentor) use ($ocean) {
                $mentorOcean = $mentor->personalityAssessment?->getOceanScores() ?? [];
                $compatibility = $this->calculateOceanCompatibility($ocean, $mentorOcean);
                $mentor->compatibility_score = $compatibility;
                return $mentor;
            })
            ->sortByDesc('compatibility_score')
            ->take(6)
            ->values();

        // Recommend sessions based on the user's existing relationships
        $recommendedSessions = Session::forUser($user)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->with('relationship.mentor')
            ->latest('scheduled_at')
            ->take(10)
            ->get();

        // Recommend evaluations based on personality traits
        $recommendedEvaluations = $this->getRecommendedEvaluations($ocean, $user);

        return response()->json([
            'recommended_mentors' => RecommendationMentorResource::collection($recommendedMentors)->resolve($request),
            'recommended_sessions' => RecommendationSessionResource::collection($recommendedSessions)->resolve($request),
            'recommended_evaluations' => $recommendedEvaluations,
            'user_ocean' => $ocean,
        ]);
    }

    private function calculateOceanCompatibility(array $userOcean, array $mentorOcean): float
    {
        if (empty($userOcean) || empty($mentorOcean)) {
            return 0;
        }

        $dimensions = ['O', 'C', 'E', 'A', 'N'];
        $scores = [];

        foreach ($dimensions as $dim) {
            $u = $userOcean[$dim] ?? 50;
            $m = $mentorOcean[$dim] ?? 50;
            // For N (Neuroticism), lower difference is better
            // For others, moderate-to-high compatibility based on complementarity
            if ($dim === 'N') {
                $scores[] = max(0, 100 - abs($u - $m));
            } elseif ($dim === 'E' || $dim === 'O') {
                // Similarity is good for extraversion and openness
                $scores[] = max(0, 100 - abs($u - $m));
            } else {
                // For C and A, moderate complementarity
                $diff = abs($u - $m);
                $scores[] = $diff > 30 ? max(0, 100 - $diff) : 70 + (30 - $diff) / 3;
            }
        }

        return round(array_sum($scores) / count($scores), 1);
    }

    private function getRecommendedEvaluations(array $ocean, User $user): array
    {
        $recommendations = [];

        $openness = $ocean['O'] ?? 50;
        $conscientiousness = $ocean['C'] ?? 50;
        $extraversion = $ocean['E'] ?? 50;
        $agreeableness = $ocean['A'] ?? 50;
        $neuroticism = $ocean['N'] ?? 50;

        if ($openness >= 60) {
            $recommendations['creatividad'] = [
                'reason' => 'recommendations.high_openness',
            ];
        }

        if ($conscientiousness >= 60) {
            $recommendations['liderazgo'] = [
                'reason' => 'recommendations.high_conscientiousness',
            ];
        }

        if ($extraversion >= 60) {
            $recommendations['inteligencia-emocional'] = [
                'reason' => 'recommendations.high_extraversion',
            ];
        }

        if ($agreeableness >= 60) {
            $recommendations['inteligencia-emocional'] = [
                'reason' => 'recommendations.high_agreeableness',
            ];
        }

        if ($neuroticism >= 60) {
            $recommendations['estres-burnout'] = [
                'reason' => 'recommendations.high_neuroticism',
            ];
        }

        if ($conscientiousness < 40) {
            $recommendations['motivacion'] = [
                'reason' => 'recommendations.low_conscientiousness',
            ];
        }

        if ($extraversion < 40) {
            $recommendations['inteligencia-emocional'] = [
                'reason' => 'recommendations.low_extraversion',
            ];
        }

        if (empty($recommendations)) {
            $recommendations['bienestar-integral'] = [
                'reason' => 'recommendations.default',
            ];
        }

        $evaluations = LearningAssessment::where('is_active', true)
            ->whereIn('slug', array_keys($recommendations))
            ->where(function ($q) use ($user) {
                $q->whereNull('client_types')
                    ->orWhereJsonLength('client_types', 0);

                if ($user->client_type) {
                    $q->orWhereJsonContains('client_types', $user->client_type);
                }
            })
            ->get()
            ->map(function (LearningAssessment $evaluation) use ($recommendations) {
                $locale = app()->getLocale();
                $reasonKey = $recommendations[$evaluation->slug]['reason'] ?? 'recommendations.default';

                return [
                    'id' => $evaluation->id,
                    'title' => $evaluation->title[$locale] ?? $evaluation->title['es'] ?? $evaluation->slug,
                    'description' => $evaluation->description[$locale] ?? $evaluation->description['es'] ?? '',
                    'reason' => __($reasonKey),
                    'type' => $evaluation->category ?? 'desarrollo',
                ];
            })
            ->values()
            ->all();

        if (empty($evaluations)) {
            $evaluations = LearningAssessment::where('is_active', true)
                ->where(function ($q) use ($user) {
                    $q->whereNull('client_types')
                        ->orWhereJsonLength('client_types', 0);

                    if ($user->client_type) {
                        $q->orWhereJsonContains('client_types', $user->client_type);
                    }
                })
                ->latest()
                ->take(6)
                ->get()
                ->map(function (LearningAssessment $evaluation) {
                    $locale = app()->getLocale();

                    return [
                        'id' => $evaluation->id,
                        'title' => $evaluation->title[$locale] ?? $evaluation->title['es'] ?? $evaluation->slug,
                        'description' => $evaluation->description[$locale] ?? $evaluation->description['es'] ?? '',
                        'reason' => __('recommendations.complement'),
                        'type' => $evaluation->category ?? 'desarrollo',
                    ];
                })
                ->values()
                ->all();
        }

        return array_slice($evaluations, 0, 6);
    }
}
