<?php

namespace App\Services;

use App\Models\Cohort;
use App\Models\Company;
use App\Models\FamilyGroup;
use App\Models\LearningPath;
use App\Models\LearningResource;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContentAccessService
{
    public function learningPaths(User $user): Builder
    {
        $query = LearningPath::query()->where('is_active', true);

        if ($user->isAdmin()) {
            return $query;
        }

        $query->where(function (Builder $pathQuery) use ($user) {
            $pathQuery->whereNull('client_type')
                ->orWhere('client_type', $user->client_type);
        });

        if (!$this->hasEntitledContent($user)) {
            $query->whereHas('modules.resources', fn (Builder $resourceQuery) =>
                $resourceQuery->where('is_free', true)
            );
        }

        return $query;
    }

    public function resources(User $user): Builder
    {
        $query = LearningResource::query()->whereHas('module.learningPath', function (Builder $pathQuery) use ($user) {
            $pathQuery->where('is_active', true);

            if (!$user->isAdmin()) {
                $pathQuery->where(function (Builder $clientQuery) use ($user) {
                    $clientQuery->whereNull('client_type')
                        ->orWhere('client_type', $user->client_type);
                });
            }
        });

        if (!$user->isAdmin() && !$this->hasEntitledContent($user)) {
            $query->where('is_free', true);
        }

        return $query;
    }

    public function hasEntitledContent(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->activePlanForModel($user, $user->client_type)) {
            return true;
        }

        return match ($user->client_type) {
            'familiar' => $this->familyPlanForUser($user) !== null,
            'grupal' => $this->cohortPlanForUser($user) !== null,
            'empresa' => $this->companyPlanForUser($user) !== null,
            default => false,
        };
    }

    public function hasFeature(User $user, string $feature): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $plan = $this->activePlanForModel($user, $user->client_type);

        if (!$plan) {
            $plan = match ($user->client_type) {
                'familiar' => $this->familyPlanForUser($user),
                'grupal' => $this->cohortPlanForUser($user),
                'empresa' => $this->companyPlanForUser($user),
                default => null,
            };
        }

        if (!$plan) {
            return false;
        }

        $aliases = match (strtolower($feature)) {
            'evaluations' => ['evaluations', 'evaluaciones', 'avaliacoes', 'avaliações'],
            default => [strtolower($feature)],
        };

        return collect($plan->features ?? [])
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->intersect($aliases)
            ->isNotEmpty();
    }

    public function activePlanForModel(Model $source, ?string $clientType): ?Plan
    {
        if (!$clientType) {
            return null;
        }

        if (method_exists($source, 'plan')) {
            $directPlan = $source->plan()
                ->where('is_active', true)
                ->where('client_type', $clientType)
                ->first();

            if ($directPlan) {
                return $directPlan;
            }
        }

        if (!method_exists($source, 'subscriptions')) {
            return null;
        }

        return $source->subscriptions()
            ->current()
            ->whereHas('plan', fn (Builder $planQuery) => $this->applyActivePlanFilter($planQuery, $clientType))
            ->with('plan')
            ->first()?->plan;
    }

    public function activePlanForSource(string $sourceType, string $sourceId, ?string $clientType): ?Plan
    {
        $source = match ($sourceType) {
            'user', User::class => User::find($sourceId),
            'family_group', FamilyGroup::class => FamilyGroup::find($sourceId),
            'cohort', Cohort::class => Cohort::find($sourceId),
            'company', Company::class => Company::find($sourceId),
            default => null,
        };

        if (!$source) {
            return null;
        }

        $plan = $this->activePlanForModel($source, $clientType);
        if ($plan) {
            return $plan;
        }

        $ownerId = match (true) {
            $source instanceof FamilyGroup => $source->head_user_id,
            $source instanceof Cohort => $source->created_by,
            $source instanceof Company => $source->admin_id,
            default => null,
        };

        $owner = $ownerId ? User::find($ownerId) : null;

        return $owner ? $this->activePlanForModel($owner, $clientType) : null;
    }

    private function familyHasPlan(User $user): bool
    {
        return $this->familyPlanForUser($user) !== null;
    }

    private function familyPlanForUser(User $user): ?Plan
    {
        $groups = FamilyGroup::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('head_user_id', $user->id)
                    ->orWhereHas('members', fn (Builder $members) => $members->where('user_id', $user->id));
            })
            ->get();

        foreach ($groups as $group) {
            $plan = $this->activePlanForModel($group, 'familiar');
            if ($plan) {
                return $plan;
            }
        }

        return null;
    }

    private function cohortHasPlan(User $user): bool
    {
        return $this->cohortPlanForUser($user) !== null;
    }

    private function cohortPlanForUser(User $user): ?Plan
    {
        $cohorts = Cohort::query()
            ->whereHas('members', fn (Builder $members) => $members
                ->where('user_id', $user->id)
                ->where('status', 'active'))
            ->get();

        foreach ($cohorts as $cohort) {
            $plan = $this->activePlanForModel($cohort, 'grupal');
            if ($plan) {
                return $plan;
            }
        }

        return null;
    }

    private function companyHasPlan(User $user): bool
    {
        return $this->companyPlanForUser($user) !== null;
    }

    private function companyPlanForUser(User $user): ?Plan
    {
        $companies = Company::query()
            ->where(function (Builder $query) use ($user) {
                $query->whereKey($user->company_id)
                    ->orWhere('admin_id', $user->id)
                    ->orWhereHas('employees', fn (Builder $employees) => $employees
                        ->where('user_id', $user->id)
                        ->where('status', 'active'));
            })
            ->get();

        foreach ($companies as $company) {
            $plan = $this->activePlanForModel($company, 'empresa');
            if ($plan) {
                return $plan;
            }
        }

        return null;
    }

    private function applyActivePlanFilter(Builder $query, string $clientType): Builder
    {
        return $query->where('is_active', true)->where('client_type', $clientType);
    }
}
