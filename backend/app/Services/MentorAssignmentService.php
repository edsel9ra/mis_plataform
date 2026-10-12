<?php

namespace App\Services;

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Illuminate\Support\Collection;

class MentorAssignmentService
{
    private const SOURCE_BY_TYPE = [
        'personal' => 'user',
        'familiar' => 'family_group',
        'grupal' => 'cohort',
        'empresa' => 'company',
    ];

    public function __construct(
        private PlanEntitlementService $entitlements,
    ) {}

    /**
     * @return Collection<int, array{user: User, assignments: array<int, array<string, mixed>>}>
     */
    public function assignedUsers(User $mentor): Collection
    {
        if (!$mentor->is_active || !$mentor->isMentor() || !isset(self::SOURCE_BY_TYPE[$mentor->client_type])) {
            return collect();
        }

        $sourceType = self::SOURCE_BY_TYPE[$mentor->client_type];
        $relationships = MentorshipRelationship::query()
            ->active()
            ->where('mentor_id', $mentor->id)
            ->where('type', $mentor->client_type)
            ->whereIn('source_type', [$sourceType, $this->sourceClass($sourceType)])
            ->with('source')
            ->orderBy('created_at')
            ->get();

        $contexts = $relationships
            ->map(fn (MentorshipRelationship $relationship) => $relationship->source)
            ->filter()
            ->unique(fn ($context) => $context->getKey())
            ->values();
        $usersByContext = $this->usersByContext($sourceType, $contexts);

        $users = collect();

        foreach ($relationships as $relationship) {
            $context = $relationship->source;

            if (!$context) {
                continue;
            }

            $access = $this->entitlements->forModel($context, $relationship->type);
            if (!$this->shouldIncludeAssignment($relationship->type, $access['status'])) {
                continue;
            }

            foreach ($usersByContext[$context->getKey()] ?? [] as $user) {
                if (!$user->is_active) {
                    continue;
                }

                $assignment = $this->assignmentSummary($relationship, $context, $access);
                $existing = $users->get($user->id);

                if ($existing) {
                    $existing['assignments'][] = $assignment;
                    $users->put($user->id, $existing);
                } else {
                    $users->put($user->id, [
                        'user' => $user,
                        'assignments' => [$assignment],
                    ]);
                }
            }
        }

        $assessmentUserIds = $users->keys()->all();
        if ($assessmentUserIds !== []) {
            User::query()
                ->whereIn('id', $assessmentUserIds)
                ->with('personalityAssessment')
                ->get()
                ->each(function (User $user) use ($users) {
                    $entry = $users->get($user->id);
                    $entry['user'] = $user;
                    $users->put($user->id, $entry);
                });
        }

        return $users->values();
    }

    /**
     * @return array{user: User, relationship: MentorshipRelationship, assignment: array<string, mixed>}
     */
    public function authorizePair(User $mentor, User $target, string $relationshipId): array
    {
        $entry = $this->assignedUsers($mentor)->first(
            fn (array $entry) => $entry['user']->id === $target->id
        );

        $assignment = $entry
            ? collect($entry['assignments'])->firstWhere('relationship_id', $relationshipId)
            : null;

        abort_unless($assignment, 404);

        $relationship = MentorshipRelationship::query()
            ->active()
            ->where('mentor_id', $mentor->id)
            ->find($relationshipId);

        abort_unless($relationship, 404);

        return [
            'user' => $entry['user'],
            'relationship' => $relationship,
            'assignment' => $assignment,
        ];
    }

    public static function expectedSourceType(string $relationshipType): ?string
    {
        return self::SOURCE_BY_TYPE[$relationshipType] ?? null;
    }

    private function sourceClass(string $sourceType): string
    {
        return match ($sourceType) {
            'user' => User::class,
            'family_group' => FamilyGroup::class,
            'cohort' => Cohort::class,
            'company' => Company::class,
            default => $sourceType,
        };
    }

    private function shouldIncludeAssignment(string $type, string $planStatus): bool
    {
        if ($type === 'personal') {
            return true;
        }

        // Existing group contexts keep their historical filtering behavior.
        // Personal assignments are deliberately retained so the workspace can
        // explain why an assignment is locked instead of hiding it.
        return in_array($planStatus, ['active', 'no_plan'], true);
    }

    /** @return array<string, Collection<int, User>> */
    private function usersByContext(string $sourceType, Collection $contexts): array
    {
        if ($sourceType === 'user') {
            return $contexts
                ->filter(fn ($context) => $context instanceof User)
                ->mapWithKeys(fn (User $context) => [$context->id => collect([$context])])
                ->all();
        }

        $idsByContext = $contexts->mapWithKeys(fn ($context) => [
            $context->getKey() => collect(),
        ]);

        if ($sourceType === 'family_group') {
            foreach ($contexts as $context) {
                if ($context instanceof FamilyGroup) {
                    $idsByContext[$context->id]->push($context->head_user_id);
                }
            }

            FamilyMember::query()
                ->whereIn('family_group_id', $contexts->pluck('id'))
                ->whereNotNull('user_id')
                ->get(['family_group_id', 'user_id'])
                ->each(fn (FamilyMember $member) => $idsByContext[$member->family_group_id]->push($member->user_id));
        }

        if ($sourceType === 'cohort') {
            CohortMember::query()
                ->active()
                ->whereIn('cohort_id', $contexts->pluck('id'))
                ->get(['cohort_id', 'user_id'])
                ->each(fn (CohortMember $member) => $idsByContext[$member->cohort_id]->push($member->user_id));
        }

        if ($sourceType === 'company') {
            foreach ($contexts as $context) {
                if ($context instanceof Company) {
                    $idsByContext[$context->id]->push($context->admin_id);
                }
            }

            Employee::query()
                ->active()
                ->whereIn('company_id', $contexts->pluck('id'))
                ->get(['company_id', 'user_id'])
                ->each(fn (Employee $employee) => $idsByContext[$employee->company_id]->push($employee->user_id));
        }

        $userIds = $idsByContext->flatten()->filter()->unique()->values();
        $users = $userIds->isEmpty()
            ? collect()
            : User::query()->active()->whereIn('id', $userIds)->get()->keyBy('id');

        return $idsByContext->mapWithKeys(function (Collection $ids, string $contextId) use ($users) {
            return [
                $contextId => $ids
                    ->filter()
                    ->unique()
                    ->map(fn (string $userId) => $users->get($userId))
                    ->filter()
                    ->values(),
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    private function assignmentSummary(MentorshipRelationship $relationship, mixed $context, array $access): array
    {
        $plan = $access['plan'];
        $sourceType = MentorshipRelationship::canonicalSourceType($relationship->source_type);

        return [
            'relationship_id' => $relationship->id,
            'type' => $relationship->type,
            'context' => [
                'type' => $sourceType,
                'id' => $relationship->source_id,
                'name' => match ($sourceType) {
                    'user' => $context->full_name,
                    'family_group' => $context->family_name,
                    'cohort' => $context->name,
                    'company' => $context->legal_name,
                },
            ],
            'plan' => $plan ? [
                'id' => $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
                'client_type' => $plan->client_type,
            ] : null,
            'plan_status' => $access['status'],
            'locked' => $access['locked'],
            'lock_reason' => $access['lock_reason'],
            'entitlements' => $access['entitlements'],
        ];
    }
}
