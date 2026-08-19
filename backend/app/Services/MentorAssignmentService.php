<?php

namespace App\Services;

use App\Models\Cohort;
use App\Models\Company;
use App\Models\FamilyGroup;
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

        $users = collect();

        foreach ($relationships as $relationship) {
            $context = $relationship->source;

            if (!$this->contextHasApplicablePlan($context, $relationship->type)) {
                continue;
            }

            foreach ($this->usersForContext($sourceType, $context) as $user) {
                if (!$user->is_active) {
                    continue;
                }

                $assignment = $this->assignmentSummary($relationship, $context);
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

    private function contextHasApplicablePlan(mixed $context, string $type): bool
    {
        if (!$context) {
            return false;
        }

        if (!property_exists($context, 'plan_id') && !array_key_exists('plan_id', $context->getAttributes())) {
            return true;
        }

        if (!$context->plan_id) {
            return true;
        }

        return $context->plan()
            ->where('is_active', true)
            ->where('client_type', $type)
            ->exists();
    }

    /** @return Collection<int, User> */
    private function usersForContext(string $sourceType, mixed $context): Collection
    {
        if ($sourceType === 'user') {
            return $context instanceof User ? collect([$context]) : collect();
        }

        if ($sourceType === 'family_group' && $context instanceof FamilyGroup) {
            $memberIds = $context->members()->whereNotNull('user_id')->pluck('user_id');

            return User::query()
                ->whereIn('id', $memberIds->push($context->head_user_id)->unique())
                ->active()
                ->get();
        }

        if ($sourceType === 'cohort' && $context instanceof Cohort) {
            return User::query()
                ->whereIn('id', $context->members()->active()->select('user_id'))
                ->active()
                ->get();
        }

        if ($sourceType === 'company' && $context instanceof Company) {
            $employeeIds = $context->employees()->active()->pluck('user_id');

            return User::query()
                ->whereIn('id', $employeeIds->push($context->admin_id)->unique())
                ->active()
                ->get();
        }

        return collect();
    }

    /** @return array<string, mixed> */
    private function assignmentSummary(MentorshipRelationship $relationship, mixed $context): array
    {
        $plan = $context instanceof User || !$context->plan_id
            ? null
            : $context->plan()->first();

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
