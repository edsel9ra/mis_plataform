<?php

namespace App\Services\Progress;

use App\Models\Challenge;
use App\Models\MentorshipRelationship;
use App\Models\Objective;
use App\Models\Session;
use App\Models\User;
use App\Services\PlanEntitlementService;
use App\Services\Progress\Calculators\ObjectiveMetricsCalculator;
use Carbon\CarbonImmutable;

/**
 * T-079 — mentor portfolio flags (RF-9 CA-9.4) and reuse of the individual
 * panel for a mentee (RF-9).
 *
 * Only active mentor relationships are considered; a mentee whose plan lacks
 * the mentor workspace is flagged as locked instead of hidden (CA-9.2).
 */
class MentorProgressService
{
    public function __construct(
        private ObjectiveMetricsCalculator $objectives,
        private PlanEntitlementService $entitlements,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function portfolio(User $mentor): array
    {
        $now = CarbonImmutable::now();
        $inactivityDays = (int) config('progress.inactivity_days', 30);

        $relationships = MentorshipRelationship::query()
            ->where('mentor_id', $mentor->id)
            ->whereIn('source_type', ['user', User::class])
            ->where('status', 'active')
            ->with('source')
            ->get();

        $items = [];

        foreach ($relationships as $relationship) {
            $mentee = $relationship->source;

            if (! $mentee instanceof User) {
                continue;
            }

            $objectiveModels = Objective::query()
                ->forOwner('user', $mentee->id)
                ->individual()
                ->get();

            $classification = $this->objectives->classify(
                $objectiveModels->map(fn (Objective $objective): array => $objective->toArray())->all(),
                $now,
            );

            $pendingActivities = Challenge::query()
                ->where('mentee_id', $mentee->id)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->count();

            $lastSessionAt = Session::query()
                ->where('relationship_id', $relationship->id)
                ->where('status', 'completed')
                ->max('scheduled_at');

            $lastActivityAt = collect([
                $lastSessionAt,
                $objectiveModels->max('updated_at'),
                Challenge::query()->where('mentee_id', $mentee->id)->max('updated_at'),
            ])->filter()->max();

            $noActivity = $lastActivityAt === null
                || CarbonImmutable::parse($lastActivityAt)->lt($now->subDays($inactivityDays));

            $access = $this->entitlements->forModel($mentee, 'personal');

            $items[] = [
                'mentee' => [
                    'id' => $mentee->id,
                    'name' => $mentee->full_name,
                ],
                'relationship_id' => $relationship->id,
                'objectives_overdue' => $classification['overdue'],
                'objectives_pending' => $classification['pending'],
                'activities_pending' => $pendingActivities,
                'no_activity' => $noActivity,
                'last_activity_at' => $lastActivityAt === null
                    ? null
                    : CarbonImmutable::parse($lastActivityAt)->toIso8601String(),
                'workspace_locked' => ! $this->entitlements->allows(
                    $access['plan'],
                    PlanEntitlementService::MENTOR_WORKSPACE,
                ),
            ];
        }

        usort($items, function (array $a, array $b): int {
            return [$b['no_activity'], $b['objectives_overdue'], $b['activities_pending']]
                <=> [$a['no_activity'], $a['objectives_overdue'], $a['activities_pending']];
        });

        return $items;
    }
}
