<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AssessmentResource;
use App\Http\Resources\MentorResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SessionResource;
use App\Http\Resources\UserResource;
use App\Models\MentorshipRelationship;
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
            ->paginate($request->per_page ?? 20);

        return response()->json($users);
    }

    public function userShow(string $id): JsonResponse
    {
        $user = User::with(['personalityAssessment', 'company', 'skills.skillTag'])
            ->withCount('mentorRelationships')
            ->findOrFail($id);

        return response()->json(new UserResource($user));
    }

    public function userStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'sex' => ['nullable', 'string', 'in:M,F,N'],
            'birth_date' => ['nullable', 'date'],
            'client_type' => ['required', 'string', 'in:personal,familiar,grupal,empresa'],
            'role' => ['required', 'string', 'in:super_admin,admin,company_admin,employee,mentor,mentee'],
            'locale' => ['nullable', 'string', 'in:es,en,pt'],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json(new UserResource($user), 201);
    }

    public function userUpdate(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $id],
            'password' => ['sometimes', 'string', 'min:8'],
            'sex' => ['sometimes', 'string', 'in:M,F,N'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'client_type' => ['sometimes', 'string', 'in:personal,familiar,grupal,empresa'],
            'role' => ['sometimes', 'string', 'in:super_admin,admin,company_admin,employee,mentor,mentee'],
            'locale' => ['sometimes', 'string', 'in:es,en,pt'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json(new UserResource($user));
    }

    public function userDestroy(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    // ──────────────────────────────────────────────
    // Mentors CRUD
    // ──────────────────────────────────────────────

    public function mentors(Request $request): JsonResponse
    {
        $mentors = User::byRole('mentor')
            ->with(['skills.skillTag', 'personalityAssessment', 'mentorRelationships' => function ($q) {
                $q->whereIn('status', ['active', 'completed']);
            }])
            ->withCount(['mentorRelationships as active_mentees_count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->paginate($request->per_page ?? 20);

        return response()->json($mentors);
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

        return response()->json($mentor);
    }

    public function mentorStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'sex' => ['nullable', 'string', 'in:M,F,N'],
            'birth_date' => ['nullable', 'date'],
            'client_type' => ['required', 'string', 'in:personal,familiar,grupal,empresa'],
            'locale' => ['nullable', 'string', 'in:es,en,pt'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['role'] = 'mentor';

        $user = User::create($validated);

        return response()->json(new UserResource($user), 201);
    }

    public function mentorUpdate(Request $request, string $id): JsonResponse
    {
        $mentor = User::byRole('mentor')->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $id],
            'password' => ['sometimes', 'string', 'min:8'],
            'sex' => ['sometimes', 'string', 'in:M,F,N'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'client_type' => ['sometimes', 'string', 'in:personal,familiar,grupal,empresa'],
            'locale' => ['sometimes', 'string', 'in:es,en,pt'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $mentor->update($validated);

        return response()->json(new UserResource($mentor));
    }

    public function mentorDestroy(string $id): JsonResponse
    {
        $mentor = User::byRole('mentor')->findOrFail($id);
        $mentor->delete();

        return response()->json(['message' => 'Mentor deleted successfully']);
    }

    // ──────────────────────────────────────────────
    // Sessions CRUD
    // ──────────────────────────────────────────────

    public function sessions(Request $request): JsonResponse
    {
        $query = Session::with(['relationship.mentor', 'relationship.source', 'attendees.user']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        $sessions = $query->latest('scheduled_at')->paginate($request->per_page ?? 20);

        return response()->json($sessions);
    }

    public function sessionShow(string $id): JsonResponse
    {
        $session = Session::with(['relationship.mentor', 'relationship.source', 'attendees.user'])
            ->findOrFail($id);

        return response()->json($session);
    }

    public function sessionStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'relationship_id' => ['required', 'exists:mentorship_relationships,id'],
            'session_type' => ['required', 'string', 'in:one_on_one,group,workshop,assessment'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'status' => ['sometimes', 'string', 'in:scheduled,in_progress,completed,canceled'],
        ]);

        $session = Session::create($validated);

        return response()->json(new SessionResource($session), 201);
    }

    public function sessionUpdate(Request $request, string $id): JsonResponse
    {
        $session = Session::findOrFail($id);

        $validated = $request->validate([
            'relationship_id' => ['sometimes', 'exists:mentorship_relationships,id'],
            'session_type' => ['sometimes', 'string', 'in:one_on_one,group,workshop,assessment'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['sometimes', 'date'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'status' => ['sometimes', 'string', 'in:scheduled,in_progress,completed,canceled'],
        ]);

        $session->update($validated);

        return response()->json(new SessionResource($session));
    }

    public function sessionUpdateStatus(Request $request, string $id): JsonResponse
    {
        $session = Session::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:scheduled,in_progress,completed,canceled'],
        ]);

        $session->update($validated);

        return response()->json(new SessionResource($session));
    }

    public function sessionDestroy(string $id): JsonResponse
    {
        $session = Session::findOrFail($id);
        $session->delete();

        return response()->json(['message' => 'Session deleted successfully']);
    }

    // ──────────────────────────────────────────────
    // Personality Assessments CRUD
    // ──────────────────────────────────────────────

    public function assessments(Request $request): JsonResponse
    {
        $query = PersonalityAssessment::with('user');

        if ($request->filled('completed')) {
            $query->whereNotNull('completed_at');
        }

        $assessments = $query->latest()->paginate($request->per_page ?? 20);

        return response()->json($assessments);
    }

    public function assessmentShow(string $id): JsonResponse
    {
        $assessment = PersonalityAssessment::with('user')->findOrFail($id);

        return response()->json($assessment);
    }

    public function assessmentStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'test_version' => ['required', 'string', 'in:ipip-neo-120,ipip-neo-300'],
            'answers' => ['nullable', 'array'],
            'results' => ['nullable', 'array'],
            'raw_scores' => ['nullable', 'array'],
            'completed_at' => ['nullable', 'date'],
        ]);

        $assessment = PersonalityAssessment::create($validated);

        return response()->json($assessment, 201);
    }

    public function assessmentUpdate(Request $request, string $id): JsonResponse
    {
        $assessment = PersonalityAssessment::findOrFail($id);

        $validated = $request->validate([
            'test_version' => ['sometimes', 'string', 'in:ipip-neo-120,ipip-neo-300'],
            'answers' => ['nullable', 'array'],
            'results' => ['nullable', 'array'],
            'raw_scores' => ['nullable', 'array'],
            'completed_at' => ['nullable', 'date'],
        ]);

        $assessment->update($validated);

        return response()->json($assessment);
    }

    public function assessmentDestroy(string $id): JsonResponse
    {
        $assessment = PersonalityAssessment::findOrFail($id);
        $assessment->delete();

        return response()->json(['message' => 'Assessment deleted successfully']);
    }

    // ──────────────────────────────────────────────
    // Plans
    // ──────────────────────────────────────────────

    public function plans(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            $plans = Plan::withCount('subscriptions')->paginate(20);

            return response()->json($plans);
        }

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'client_type' => ['required', 'string', 'in:personal,familiar,grupal,empresa'],
                'name' => ['required', 'array'],
                'name.es' => ['required', 'string'],
                'name.en' => ['required', 'string'],
                'name.pt' => ['required', 'string'],
                'description' => ['nullable', 'array'],
                'price_monthly' => ['required', 'numeric', 'min:0'],
                'price_yearly' => ['required', 'numeric', 'min:0'],
                'max_sessions_per_month' => ['required', 'integer', 'min:1'],
                'max_members' => ['required', 'integer', 'min:1'],
                'max_mentors' => ['required', 'integer', 'min:1'],
                'features' => ['nullable', 'array'],
            ]);

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
        $usersByType = User::selectRaw('client_type, count(*) as total')
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

    // ──────────────────────────────────────────────
    // Personality-based Recommendations
    // ──────────────────────────────────────────────

    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();

        $assessment = $user->personalityAssessment;

        if (!$assessment || !$assessment->completed_at) {
            return response()->json([
                'message' => 'Complete your personality test first',
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
        $recommendedSessions = Session::whereIn('relationship_id', function ($q) use ($user) {
                $q->select('id')
                  ->from('mentorship_relationships')
                  ->where('mentor_id', $user->id)
                  ->orWhere(function ($sub) use ($user) {
                      $sub->where('source_type', 'App\\Models\\User')
                          ->where('source_id', $user->id);
                  });
            })
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->with(['relationship.mentor', 'attendees.user'])
            ->latest('scheduled_at')
            ->take(10)
            ->get();

        // Recommend evaluations based on personality traits
        $recommendedEvaluations = $this->getRecommendedEvaluations($ocean);

        return response()->json([
            'recommended_mentors' => $recommendedMentors,
            'recommended_sessions' => $recommendedSessions,
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

    private function getRecommendedEvaluations(array $ocean): array
    {
        $evaluations = [];

        $openness = $ocean['O'] ?? 50;
        $conscientiousness = $ocean['C'] ?? 50;
        $extraversion = $ocean['E'] ?? 50;
        $agreeableness = $ocean['A'] ?? 50;
        $neuroticism = $ocean['N'] ?? 50;

        if ($openness >= 60) {
            $evaluations[] = [
                'id' => 'eval-creative',
                'title' => 'Evaluación de Pensamiento Creativo',
                'description' => 'Evalúa tu capacidad de pensamiento divergente y creatividad.',
                'reason' => 'Tu alta apertura sugiere gran potencial creativo.',
                'type' => 'cognitive',
            ];
        }

        if ($conscientiousness >= 60) {
            $evaluations[] = [
                'id' => 'eval-leadership',
                'title' => 'Evaluación de Liderazgo',
                'description' => 'Mide tus habilidades de liderazgo y organización.',
                'reason' => 'Tu alta responsabilidad indica capacidad de liderazgo.',
                'type' => 'skill',
            ];
        }

        if ($extraversion >= 60) {
            $evaluations[] = [
                'id' => 'eval-communication',
                'title' => 'Evaluación de Comunicación',
                'description' => 'Analiza tus habilidades de comunicación interpersonal.',
                'reason' => 'Tu alta extraversión es ideal para roles de comunicación.',
                'type' => 'skill',
            ];
        }

        if ($agreeableness >= 60) {
            $evaluations[] = [
                'id' => 'eval-teamwork',
                'title' => 'Evaluación de Trabajo en Equipo',
                'description' => 'Evalúa tu capacidad de colaboración y empatía.',
                'reason' => 'Tu alta amabilidad favorece el trabajo colaborativo.',
                'type' => 'behavioral',
            ];
        }

        if ($neuroticism >= 60) {
            $evaluations[] = [
                'id' => 'eval-stress',
                'title' => 'Evaluación de Manejo del Estrés',
                'description' => 'Mide tu capacidad de resiliencia y manejo emocional.',
                'reason' => 'Identifica áreas para fortalecer tu bienestar emocional.',
                'type' => 'wellness',
            ];
        }

        if ($conscientiousness < 40) {
            $evaluations[] = [
                'id' => 'eval-productivity',
                'title' => 'Evaluación de Productividad',
                'description' => 'Identifica oportunidades para mejorar tu organización.',
                'reason' => 'Puedes fortalecer tus habilidades de planificación.',
                'type' => 'skill',
            ];
        }

        if ($extraversion < 40) {
            $evaluations[] = [
                'id' => 'eval-assertiveness',
                'title' => 'Evaluación de Asertividad',
                'description' => 'Desarrolla tu capacidad de expresar ideas con confianza.',
                'reason' => 'Fortalece tu presencia en entornos sociales.',
                'type' => 'behavioral',
            ];
        }

        // Limit to 4 evaluations
        return array_slice($evaluations, 0, 6);
    }
}
