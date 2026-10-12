<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MentorAssignedUserResource;
use App\Models\User;
use App\Services\MentorAssignmentService;
use App\Services\MentorRecommendationService;
use App\Services\MentorWorkspaceAccessService;
use App\Services\PlanEntitlementService;
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
        private MentorWorkspaceAccessService $workspaceAccess,
        private PlanEntitlementService $entitlements,
    ) {}

    public function users(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'plan_filter' => ['nullable', 'string', 'in:all,basic,premium,locked'],
        ]);
        $search = mb_strtolower(trim($validated['search'] ?? ''));
        $users = $this->assignments->assignedUsers($request->user())
            ->when($search !== '', fn ($items) => $items->filter(function (array $entry) use ($search) {
                return str_contains(mb_strtolower($entry['user']->full_name), $search);
            }))
            ->sortBy(fn (array $entry) => mb_strtolower($entry['user']->full_name))
            ->values();
        $planCounts = [
            'all' => $users->count(),
            'basic' => $users->filter(fn (array $entry) => $this->userMatchesPlanFilter($entry, 'basic'))->count(),
            'premium' => $users->filter(fn (array $entry) => $this->userMatchesPlanFilter($entry, 'premium'))->count(),
            'locked' => $users->filter(fn (array $entry) => $this->userMatchesPlanFilter($entry, 'locked'))->count(),
        ];
        $planFilter = $validated['plan_filter'] ?? 'all';
        if ($planFilter !== 'all') {
            $users = $users
                ->filter(fn (array $entry) => $this->userMatchesPlanFilter($entry, $planFilter))
                ->values();
        }
        $perPage = (int) ($validated['per_page'] ?? 20);
        $page = (int) ($validated['page'] ?? 1);
        $paginator = new LengthAwarePaginator(
            $users->forPage($page, $perPage)->values(),
            $users->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return MentorAssignedUserResource::collection($paginator)
            ->additional(['plan_counts' => $planCounts])
            ->response();
    }

    public function personality(Request $request, User $user): JsonResponse
    {
        $relationshipId = $this->relationshipId($request);
        $authorized = $this->workspaceAccess->mentorPair($request->user(), $user, $relationshipId);
        $assignment = $this->assignments->authorizePair($request->user(), $user, $relationshipId);
        if (!$this->entitlements->allowsAccess($authorized['access'], PlanEntitlementService::OCEAN)) {
            return response()->json([
                'user' => (new MentorAssignedUserResource([
                    'user' => $assignment['user'],
                    'assignments' => [$assignment['assignment']],
                ]))->resolve($request),
                'assessment' => null,
                ...$this->lockMeta($authorized),
            ]);
        }

        $assessment = $authorized['mentee']->personalityAssessment;
        $factors = $this->safeFactors($assessment?->results['factors'] ?? []);
        $isComplete = $assessment?->completed_at && count($factors) === 5;

        return response()->json([
            'user' => (new MentorAssignedUserResource([
                'user' => $assignment['user'],
                'assignments' => [$assignment['assignment']],
            ]))->resolve($request),
            'assessment' => $isComplete ? [
                'test_version' => $assessment->test_version,
                'completed_at' => $assessment->completed_at,
                'factors' => $factors,
                'facets' => $this->safeNumericArray($assessment->results['facets'] ?? []),
                'interpretation' => $this->interpretation->interpret($assessment->results['factors'] ?? []),
            ] : null,
            'locked' => false,
            'lock_reason' => null,
            'plan_status' => $authorized['access']['status'],
        ]);
    }

    public function recommendations(Request $request, User $user): JsonResponse
    {
        $relationshipId = $this->relationshipId($request);
        $authorized = $this->workspaceAccess->mentorPair($request->user(), $user, $relationshipId);
        if (!$this->entitlements->allowsAccess($authorized['access'], PlanEntitlementService::OCEAN)) {
            return response()->json([
                ...$this->lockMeta($authorized),
                'recommended_mentors' => [],
                'recommended_sessions' => [],
                'recommended_evaluations' => [],
            ]);
        }

        return response()->json(
            $this->recommendations->forTarget($authorized['mentee'], $authorized['relationship'], $request)
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

    private function userMatchesPlanFilter(array $entry, string $filter): bool
    {
        return collect($entry['assignments'] ?? [])
            ->contains(fn (array $assignment) => $this->assignmentMatchesPlanFilter($assignment, $filter));
    }

    private function assignmentMatchesPlanFilter(array $assignment, string $filter): bool
    {
        if ($filter === 'all') {
            return true;
        }

        if ($filter === 'locked') {
            return (bool) ($assignment['locked'] ?? false) || empty($assignment['plan']);
        }

        if (($assignment['locked'] ?? false) || empty($assignment['plan'])) {
            return false;
        }

        $plan = $assignment['plan'];
        $value = mb_strtolower(trim(($plan['code'] ?? '').' '.($plan['name'] ?? '')));

        return $filter === 'premium'
            ? str_contains($value, 'premium')
            : str_contains($value, 'basic') || str_contains($value, 'básic') || str_contains($value, 'basico');
    }

    /** @return array{locked: bool, lock_reason: ?string, plan_status: string} */
    private function lockMeta(array $authorized): array
    {
        return [
            'locked' => true,
            'lock_reason' => $authorized['access']['locked']
                ? $authorized['access']['lock_reason']
                : 'entitlement_missing',
            'plan_status' => $authorized['access']['status'],
        ];
    }
}
