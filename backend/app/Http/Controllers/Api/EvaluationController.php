<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AssessmentResultResource;
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

        $evaluations = $this->accessibleEvaluations($user)
            ->with(['results' => fn ($q) => $q->where('user_id', $user->id)])
            ->withCount('results')
            ->latest()
            ->paginate($request->integer('per_page', 20));

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

    public function submit(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $evaluation = $this->accessibleEvaluations($user)->findOrFail($id);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required'],
            'answers.*.option_index' => ['required', 'integer', 'min:0'],
            'time_spent_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

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

        $results = $query->paginate($request->per_page ?? 20);

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
        $byUser = [];

        foreach ($relationships as $relationship) {
            foreach ($this->userIdsForRelationship($relationship) as $userId) {
                $byUser[$userId][] = $relationship;
            }
        }

        return $byUser;
    }

    private function userIdsForRelationship(MentorshipRelationship $relationship): array
    {
        if (in_array($relationship->source_type, ['user', User::class], true)) {
            return [$relationship->source_id];
        }

        if (in_array($relationship->source_type, ['family_group', FamilyGroup::class], true)) {
            $familyHeadId = FamilyGroup::where('id', $relationship->source_id)->value('head_user_id');
            $familyMemberIds = FamilyMember::where('family_group_id', $relationship->source_id)->pluck('user_id')->all();

            return collect([$familyHeadId, ...$familyMemberIds])->filter()->unique()->values()->all();
        }

        if (in_array($relationship->source_type, ['cohort', \App\Models\Cohort::class], true)) {
            return CohortMember::where('cohort_id', $relationship->source_id)->pluck('user_id')->unique()->values()->all();
        }

        if (in_array($relationship->source_type, ['company', Company::class], true)) {
            $companyAdminId = Company::where('id', $relationship->source_id)->value('admin_id');
            $employeeIds = Employee::where('company_id', $relationship->source_id)->pluck('user_id')->all();

            return collect([$companyAdminId, ...$employeeIds])->filter()->unique()->values()->all();
        }

        return [];
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
