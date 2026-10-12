<?php

namespace App\Services\Progress;

use App\Models\CohortMember;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\Objective;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * T-074 — resolve the eligible participants of a collective (D5).
 *
 * Eligible members are active users with a current membership. Members who
 * actually have at least one individual objective are the "contributing" base
 * used for the aggregate and its privacy threshold.
 */
class EligibleParticipantsResolver
{
    /**
     * Active member ids of a collective, regardless of objectives.
     *
     * @return array<int, string>
     */
    public function memberIds(string $type, string $id): array
    {
        $ids = match ($type) {
            'family_group' => collect([FamilyGroup::whereKey($id)->value('head_user_id')])
                ->merge(FamilyMember::where('family_group_id', $id)->pluck('user_id'))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'cohort' => CohortMember::active()->where('cohort_id', $id)->pluck('user_id')->unique()->values()->all(),
            'company' => Employee::active()->where('company_id', $id)->pluck('user_id')->unique()->values()->all(),
            default => [],
        };

        if ($ids === []) {
            return [];
        }

        return User::query()->active()->whereIn('id', $ids)->pluck('id')->all();
    }

    /**
     * Member id -> list of individual objectives (as arrays) for the aggregate.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function objectivesByMember(string $type, string $id): array
    {
        $memberIds = $this->memberIds($type, $id);

        if ($memberIds === []) {
            return [];
        }

        $objectives = Objective::query()
            ->individual()
            ->where('owner_type', 'user')
            ->whereIn('owner_id', $memberIds)
            ->get()
            ->groupBy('owner_id');

        $byMember = [];

        foreach ($memberIds as $memberId) {
            $byMember[$memberId] = ($objectives[$memberId] ?? collect())
                ->map(fn (Objective $objective): array => $objective->toArray())
                ->all();
        }

        return $byMember;
    }

    /**
     * Collectives (family groups, cohorts, companies) the user currently
     * belongs to, so the panel can expose the corresponding aggregate.
     *
     * @return array<int, array{type: string, id: string, name: string}>
     */
    public function collectivesForUser(User $user): array
    {
        $collectives = [];

        foreach (FamilyGroup::where('head_user_id', $user->id)->get() as $group) {
            $collectives[] = ['type' => 'family_group', 'id' => $group->id, 'name' => $group->family_name];
        }

        foreach (FamilyMember::where('user_id', $user->id)->with('familyGroup')->get() as $member) {
            if ($member->familyGroup) {
                $collectives[] = [
                    'type' => 'family_group',
                    'id' => $member->familyGroup->id,
                    'name' => $member->familyGroup->family_name,
                ];
            }
        }

        foreach (CohortMember::active()->where('user_id', $user->id)->with('cohort')->get() as $member) {
            if ($member->cohort) {
                $collectives[] = ['type' => 'cohort', 'id' => $member->cohort->id, 'name' => $member->cohort->name];
            }
        }

        foreach (Employee::active()->where('user_id', $user->id)->with('company')->get() as $employee) {
            if ($employee->company) {
                $collectives[] = [
                    'type' => 'company',
                    'id' => $employee->company->id,
                    'name' => $employee->company->legal_name,
                ];
            }
        }

        return Collection::make($collectives)
            ->unique(fn (array $collective): string => $collective['type'].':'.$collective['id'])
            ->values()
            ->all();
    }

    /**
     * @return array{type: string, id: string, name: string}|null
     */
    public function nameFor(string $type, string $id): ?array
    {
        $name = match ($type) {
            'family_group' => FamilyGroup::whereKey($id)->value('family_name'),
            'cohort' => \App\Models\Cohort::whereKey($id)->value('name'),
            'company' => Company::whereKey($id)->value('legal_name'),
            default => null,
        };

        return $name === null ? null : ['type' => $type, 'id' => $id, 'name' => $name];
    }
}
