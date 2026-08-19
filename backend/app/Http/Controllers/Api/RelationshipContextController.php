<?php

namespace App\Http\Controllers\Api;

use App\Models\Cohort;
use App\Models\Company;
use App\Models\FamilyGroup;
use App\Models\MentorshipRelationship;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RelationshipContextController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mentor_id' => ['nullable', 'uuid'],
        ]);
        $user = $request->user();
        $mentor = isset($validated['mentor_id'])
            ? User::active()->where('role', 'mentor')->findOrFail($validated['mentor_id'])
            : null;

        $currentPlan = $user->subscriptions()
            ->current()
            ->with('plan')
            ->latest()
            ->first()?->plan;

        $contexts = collect([[
            'source_type' => 'user',
            'source_id' => $user->id,
            'type' => 'personal',
            'name' => $user->full_name,
            'plan' => $this->planSummary($this->applicablePlan(null, $currentPlan, 'personal')),
        ]]);

        $contexts = $contexts
            ->concat(FamilyGroup::with('plan')->where('head_user_id', $user->id)->get()->map(fn(FamilyGroup $group) => [
                'source_type' => 'family_group',
                'source_id' => $group->id,
                'type' => 'familiar',
                'name' => $group->family_name,
                'plan' => $this->planSummary($this->applicablePlan($group->plan, $currentPlan, 'familiar')),
            ]))
            ->concat(Cohort::with('plan')->where('created_by', $user->id)->get()->map(fn(Cohort $cohort) => [
                'source_type' => 'cohort',
                'source_id' => $cohort->id,
                'type' => 'grupal',
                'name' => $cohort->name,
                'plan' => $this->planSummary($this->applicablePlan($cohort->plan, $currentPlan, 'grupal')),
            ]))
            ->concat(Company::with('plan')->where('admin_id', $user->id)->get()->map(fn(Company $company) => [
                'source_type' => 'company',
                'source_id' => $company->id,
                'type' => 'empresa',
                'name' => $company->legal_name,
                'plan' => $this->planSummary($this->applicablePlan($company->plan, $currentPlan, 'empresa')),
            ]));

        if ($mentor) {
            $contexts = $contexts->where('type', $mentor->client_type)->values();
            $relationships = MentorshipRelationship::query()
                ->where('mentor_id', $mentor->id)
                ->whereIn('status', ['pending', 'active'])
                ->get()
                ->keyBy(fn(MentorshipRelationship $relationship) => "{$relationship->source_type}:{$relationship->source_id}");

            $contexts = $contexts->map(function (array $context) use ($relationships) {
                $relationship = $relationships->get("{$context['source_type']}:{$context['source_id']}");
                $context['relationship'] = $relationship ? [
                    'id' => $relationship->id,
                    'status' => $relationship->status,
                ] : null;

                return $context;
            });
        }

        return response()->json(['data' => $contexts->values()]);
    }

    private function planSummary(?Plan $plan): ?array
    {
        if (!$plan) {
            return null;
        }

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'max_sessions_per_month' => $plan->max_sessions_per_month,
            'max_members' => $plan->max_members,
            'max_mentors' => $plan->max_mentors,
        ];
    }

    private function applicablePlan(?Plan $sourcePlan, ?Plan $userPlan, string $type): ?Plan
    {
        if ($sourcePlan?->client_type === $type) {
            return $sourcePlan;
        }

        return $userPlan?->client_type === $type ? $userPlan : null;
    }
}
