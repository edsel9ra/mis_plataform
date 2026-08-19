<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MentorAssignedUserResource;
use App\Models\User;
use App\Services\MentorAssignmentService;
use App\Services\MentorRecommendationService;
use App\Services\PersonalityInterpretationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MentorDashboardController
{
    public function __construct(
        private MentorAssignmentService $assignments,
        private PersonalityInterpretationService $interpretation,
        private MentorRecommendationService $recommendations,
    ) {}

    public function users(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $search = mb_strtolower(trim($validated['search'] ?? ''));
        $users = $this->assignments->assignedUsers($request->user())
            ->when($search !== '', fn ($items) => $items->filter(function (array $entry) use ($search) {
                return str_contains(mb_strtolower($entry['user']->full_name), $search);
            }))
            ->sortBy(fn (array $entry) => mb_strtolower($entry['user']->full_name))
            ->values();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $page = (int) ($validated['page'] ?? 1);
        $paginator = new LengthAwarePaginator(
            $users->forPage($page, $perPage)->values(),
            $users->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return MentorAssignedUserResource::collection($paginator)->response();
    }

    public function personality(Request $request, User $user): JsonResponse
    {
        $relationshipId = $this->relationshipId($request);
        $authorized = $this->assignments->authorizePair($request->user(), $user, $relationshipId);
        $assessment = $authorized['user']->personalityAssessment;
        $factors = $this->safeFactors($assessment?->results['factors'] ?? []);
        $isComplete = $assessment?->completed_at && count($factors) === 5;

        return response()->json([
            'user' => (new MentorAssignedUserResource([
                'user' => $authorized['user'],
                'assignments' => [$authorized['assignment']],
            ]))->resolve($request),
            'assessment' => $isComplete ? [
                'test_version' => $assessment->test_version,
                'completed_at' => $assessment->completed_at,
                'factors' => $factors,
                'facets' => $this->safeNumericArray($assessment->results['facets'] ?? []),
                'interpretation' => $this->interpretation->interpret($assessment->results['factors'] ?? []),
            ] : null,
        ]);
    }

    public function recommendations(Request $request, User $user): JsonResponse
    {
        $relationshipId = $this->relationshipId($request);
        $authorized = $this->assignments->authorizePair($request->user(), $user, $relationshipId);

        return response()->json(
            $this->recommendations->forTarget($authorized['user'], $authorized['relationship'], $request)
        );
    }

    private function relationshipId(Request $request): string
    {
        return $request->validate([
            'relationship_id' => ['required', 'uuid'],
        ])['relationship_id'];
    }

    private function safeNumericArray(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return collect($values)
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => $value + 0)
            ->all();
    }

    private function safeFactors(mixed $values): array
    {
        return array_intersect_key(
            $this->safeNumericArray($values),
            array_flip(['O', 'C', 'E', 'A', 'N']),
        );
    }
}
