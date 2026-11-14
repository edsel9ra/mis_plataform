<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AssessmentResultResource;
use App\Http\Requests\Evaluation\SubmitEvaluationRequest;
use App\Support\LikePattern;
use App\Http\Resources\EvaluationResource;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\CohortMember;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EvaluationController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $evaluations = $this->accessibleEvaluations($user)
            ->with(['results' => fn ($q) => $q->where('user_id', $user->id)])
            ->withCount('results')
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => EvaluationResource::collection($evaluations->getCollection())->resolve($request),
            'current_page' => $evaluations->currentPage(),
            'last_page' => $evaluations->lastPage(),
            'per_page' => $evaluations->perPage(),
            'total' => $evaluations->total(),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $evaluation = $this->accessibleEvaluations($user)
            ->with(['results' => fn ($q) => $q->where('user_id', $user->id)])
            ->withCount('results')
            ->findOrFail($id);

        return response()->json((new EvaluationResource($evaluation))->resolve($request));
    }

    public function submit(SubmitEvaluationRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        $evaluation = $this->accessibleEvaluations($user)->findOrFail($id);

        $validated = $request->validated();

        $score = $this->score($evaluation, $validated['answers']);

        $result = AssessmentResult::updateOrCreate(
            [
                'user_id' => $user->id,
                'assessment_id' => $evaluation->id,
            ],
            [
                'answers' => $validated['answers'],
                'score' => $score,
                'passed' => $score >= $evaluation->passing_score,
                'time_spent_seconds' => $validated['time_spent_seconds'] ?? null,
                'completed_at' => now(),
            ],
        )->load(['user', 'assessment']);

        return response()->json(new AssessmentResultResource($result), 201);
    }

    public function mentorResults(Request $request): JsonResponse
    {
        $mentor = $request->user();

        abort_unless($mentor->isMentor(), 403);

        $relationships = MentorshipRelationship::where('mentor_id', $mentor->id)
            ->whereIn('status', ['active', 'completed'])
            ->when($request->filled('relationship_id'), fn ($q) => $q->where('id', $request->relationship_id))
            ->latest()
            ->get();

        $relationshipsByUser = $this->relationshipsByUser($relationships);
        $userIds = array_keys($relationshipsByUser);

        if ($mentor->client_type && !empty($userIds)) {
            $userIds = User::whereIn('id', $userIds)
                ->where('client_type', $mentor->client_type)
                ->pluck('id')
                ->all();
            $relationshipsByUser = array_intersect_key($relationshipsByUser, array_flip($userIds));
        }

        $query = AssessmentResult::with(['user', 'assessment'])
            ->whereIn('user_id', $userIds)
            ->latest('completed_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('assessment_id')) {
            $query->where('assessment_id', $request->assessment_id);
        }

        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where(function ($q) use ($term) {
                $q->whereHas('user', function ($userQuery) use ($term) {
                    $userQuery->where('name', 'ilike', LikePattern::contains($term))
                        ->orWhere('last_name', 'ilike', LikePattern::contains($term))
                        ->orWhere('email', 'ilike', LikePattern::contains($term));
                })->orWhereHas('assessment', function ($assessmentQuery) use ($term) {
                    $assessmentQuery->where('slug', 'ilike', LikePattern::contains($term))
                        ->orWhere('title->es', 'ilike', LikePattern::contains($term))
                        ->orWhere('title->en', 'ilike', LikePattern::contains($term));
                });
            });
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $results = $query->paginate($perPage);

        return response()->json([
            'data' => $results->getCollection()->map(function (AssessmentResult $result) use ($request, $relationshipsByUser) {
                $payload = (new AssessmentResultResource($result))->resolve($request);
                $payload['relationship'] = $this->relationshipSummary($relationshipsByUser[$result->user_id][0]);

                return $payload;
            })->values(),
            'current_page' => $results->currentPage(),
            'last_page' => $results->lastPage(),
            'per_page' => $results->perPage(),
            'total' => $results->total(),
        ]);
    }

    private function accessibleEvaluations(User $user): Builder
    {
        $query = Assessment::query()->where('is_active', true);

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->whereNull('client_types')
                ->orWhereJsonLength('client_types', 0);

            if ($user->client_type) {
                $q->orWhereJsonContains('client_types', $user->client_type);
            }
        });
    }

    private function score(Assessment $evaluation, array $answers): float
    {
        $questions = collect($evaluation->questions ?? []);

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'answers' => __('validation.required', ['attribute' => 'answers']),
            ]);
        }

        $answersByQuestion = collect($answers)->keyBy(fn ($answer) => (string) $answer['question_id']);

        if ($answersByQuestion->count() !== count($answers) || $answersByQuestion->count() !== $questions->count()) {
            throw ValidationException::withMessages([
                'answers' => __('validation.distinct', ['attribute' => 'answers']),
            ]);
        }

        $earned = 0;
        $available = 0;

        foreach ($questions as $index => $question) {
            $questionId = (string) ($question['id'] ?? $index + 1);
            $answer = $answersByQuestion->get($questionId);
            $options = $question['options'] ?? [];
            $max = max(count($options) - 1, 0);

            if (!$answer || $max <= 0 || $answer['option_index'] > $max) {
                throw ValidationException::withMessages([
                    'answers' => __('validation.in', ['attribute' => 'answers']),
                ]);
            }

            $value = (int) $answer['option_index'];
            $earned += ($question['reverse_score'] ?? false) ? $max - $value : $value;
            $available += $max;
        }

        return $available > 0 ? round(($earned / $available) * 100, 2) : 0.0;
    }

    private function relationshipsByUser($relationships): array
    {
        $bySourceType = $relationships->groupBy(
            fn (MentorshipRelationship $relationship) => MentorshipRelationship::canonicalSourceType($relationship->source_type)
        );

        $sourceIds = fn (string $type) => $bySourceType
            ->get($type, collect())
            ->pluck('source_id')
            ->filter()
            ->unique()
            ->values();

        $familyHeadBySource = FamilyGroup::whereIn('id', $sourceIds('family_group'))
            ->pluck('head_user_id', 'id');
        $familyMembersBySource = FamilyMember::whereIn('family_group_id', $sourceIds('family_group'))
            ->get(['family_group_id', 'user_id'])
            ->groupBy('family_group_id');

        $cohortMembersBySource = CohortMember::whereIn('cohort_id', $sourceIds('cohort'))
            ->where('status', 'active')
            ->get(['cohort_id', 'user_id'])
            ->groupBy('cohort_id');

        $companyAdminBySource = Company::whereIn('id', $sourceIds('company'))
            ->pluck('admin_id', 'id');
        $employeesBySource = Employee::whereIn('company_id', $sourceIds('company'))
            ->where('status', 'active')
            ->get(['company_id', 'user_id'])
            ->groupBy('company_id');

        $candidateIdsByRelationship = [];
        $allCandidateIds = [];

        foreach ($relationships as $relationship) {
            $ids = match (MentorshipRelationship::canonicalSourceType($relationship->source_type)) {
                'user' => [$relationship->source_id],
                'family_group' => [
                    $familyHeadBySource->get($relationship->source_id),
                    ...$familyMembersBySource->get($relationship->source_id, collect())->pluck('user_id')->all(),
                ],
                'cohort' => $cohortMembersBySource->get($relationship->source_id, collect())->pluck('user_id')->all(),
                'company' => [
                    $companyAdminBySource->get($relationship->source_id),
                    ...$employeesBySource->get($relationship->source_id, collect())->pluck('user_id')->all(),
                ],
                default => [],
            };

            $candidateIdsByRelationship[$relationship->id] = $ids;
            $allCandidateIds = array_merge($allCandidateIds, $ids);
        }

        $activeIds = User::query()
            ->whereIn('id', collect($allCandidateIds)->filter()->unique()->values())
            ->where('is_active', true)
            ->pluck('id')
            ->flip();

        $byUser = [];

        foreach ($relationships as $relationship) {
            foreach ($candidateIdsByRelationship[$relationship->id] ?? [] as $userId) {
                if ($userId && $activeIds->has($userId)) {
                    $byUser[$userId][] = $relationship;
                }
            }
        }

        return $byUser;
    }
    private function relationshipSummary(MentorshipRelationship $relationship): array
    {
        return [
            'id' => $relationship->id,
            'type' => $relationship->type,
            'status' => $relationship->status,
            'source_type' => $relationship->source_type,
            'source_id' => $relationship->source_id,
            'started_at' => $relationship->started_at,
            'completed_at' => $relationship->completed_at,
        ];
    }
}
