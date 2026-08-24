<?php

namespace App\Services;

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\Plan;
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
            ->where('source_type', $sourceType)
            ->with('source')
            ->orderBy('created_at')
            ->get();

        $contexts = $relationships
            ->map(fn (MentorshipRelationship $relationship) => $relationship->source)
            ->filter()
            ->unique(fn ($context) => $context->getKey())
            ->values();
        $plans = $this->plansForContexts($contexts);
        $usersByContext = $this->usersByContext($sourceType, $contexts);

        $users = collect();

        foreach ($relationships as $relationship) {
            $context = $relationship->source;

            if (!$this->contextHasApplicablePlan($context, $relationship->type, $plans)) {
                continue;
            }

            $plan = $this->applicablePlan($context, $plans);

            foreach ($usersByContext[$context->getKey()] ?? [] as $user) {
                if (!$user->is_active) {
                    continue;
                }

                $assignment = $this->assignmentSummary($relationship, $context, $plan);
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

    private function plansForContexts(Collection $contexts): Collection
    {
        $planIds = $contexts
            ->filter(fn ($context) => array_key_exists('plan_id', $context->getAttributes()))
            ->pluck('plan_id')
            ->filter()
            ->unique()
            ->values();

        if ($planIds->isEmpty()) {
            return collect();
        }

        return Plan::query()->whereIn('id', $planIds)->get()->keyBy('id');
    }

    private function contextHasApplicablePlan(mixed $context, string $type, Collection $plans): bool
    {
        if (!$context) {
            return false;
        }

        if (!array_key_exists('plan_id', $context->getAttributes()) || !$context->plan_id) {
            return true;
        }

        $plan = $plans->get($context->plan_id);

        return (bool) ($plan?->is_active && $plan->client_type === $type);
    }

    private function applicablePlan(mixed $context, Collection $plans): ?Plan
    {
        if (!$context || !array_key_exists('plan_id', $context->getAttributes()) || !$context->plan_id) {
            return null;
        }

        return $plans->get($context->plan_id);
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
    private function assignmentSummary(MentorshipRelationship $relationship, mixed $context, ?Plan $plan): array
    {
        return [
            'relationship_id' => $relationship->id,
            'type' => $relationship->type,
            'context' => [
                'type' => $relationship->source_type,
                'id' => $relationship->source_id,
                'name' => match ($relationship->source_type) {
                    'user' => $context->full_name,
                    'family_group' => $context->family_name,
                    'cohort' => $context->name,
                    'company' => $context->legal_name,
                },
            ],
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'client_type' => $plan->client_type,
            ] : null,
        ];
    }
}
