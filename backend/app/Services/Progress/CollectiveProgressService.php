<?php

namespace App\Services\Progress;

use App\Services\Progress\Calculators\CollectiveAggregateCalculator;
use App\Services\Progress\Calculators\LearningAggregateCalculator;
use App\Services\Progress\Map\CollectiveLearningAggregate;
use App\Services\Progress\Support\MetricValue;

/**
 * T-075 — build the collective aggregate (RF-10, RF-11).
 *
 * The same code path serves managers and members: the privacy threshold is
 * uniform and a suppressed aggregate never reveals the exact base size
 * (RNF-2, CA-11.3).
 */
class CollectiveProgressService
{
    public function __construct(
        private EligibleParticipantsResolver $resolver,
        private CollectiveAggregateCalculator $calculator,
        private LearningAggregateCalculator $learningCalculator,
        private PathProgressService $pathProgress,
    ) {}

    /**
     * @return array{
     *     state: string,
     *     suppressed: bool,
     *     reason: string|null,
     *     mean: int|null,
     *     sum: array{achieved: int, total: int},
     *     contributing_members: int,
     *     eligible_members: int
     * }
     */
    public function aggregate(string $type, string $id, ?int $threshold = null): array
    {
        $threshold ??= (int) config('progress.collective_privacy_threshold', CollectiveAggregateCalculator::DEFAULT_THRESHOLD);

        $byMember = $this->resolver->objectivesByMember($type, $id);

        return $this->calculator->aggregate($byMember, count($byMember), $threshold);
    }

    /**
     * Collective learning aggregate (RF-013.5–.8): mean of the comparable
     * individual activity/path rates of the same eligible base the baseline
     * uses (active membership + >=1 individual objective). Each metric is
     * suppressed independently; nothing below a threshold exposes counts,
     * contributions or member identities.
     */
    public function learningAggregate(string $type, string $id, ?int $threshold = null): CollectiveLearningAggregate
    {
        $threshold ??= (int) config('progress.collective_privacy_threshold', CollectiveAggregateCalculator::DEFAULT_THRESHOLD);

        $byMember = $this->resolver->objectivesByMember($type, $id);
        $eligibleIds = array_keys(array_filter(
            $byMember,
            fn (array $objectives): bool => $objectives !== [],
        ));

        if (count($eligibleIds) < $threshold) {
            return $this->learningCalculator->collective(count($eligibleIds), [], [], $threshold);
        }

        $perMember = $this->pathProgress->ratesForSubjects($eligibleIds);

        $activity = [];
        $groups = [];

        foreach ($eligibleIds as $memberId) {
            $member = $perMember[$memberId] ?? ['activity' => MetricValue::noData(), 'paths' => []];
            $activity[] = $member['activity'];

            foreach ($member['paths'] as $path) {
                $groups[$path['version_key']] ??= [
                    'path_id' => $path['path_id'],
                    'version_key' => $path['version_key'],
                    'title' => $path['title'],
                    'contributions' => [],
                ];
                $groups[$path['version_key']]['contributions'][] = $path['rate'];
            }
        }

        $pathGroups = array_values($groups);
        usort($pathGroups, fn (array $a, array $b): int => strcmp($a['path_id'], $b['path_id']));

        return $this->learningCalculator->collective(count($eligibleIds), $activity, $pathGroups, $threshold);
    }

    /**
     * Aggregates for every collective the user currently belongs to.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forUser(\App\Models\User $user, ?int $threshold = null): array
    {
        return array_map(function (array $collective) use ($threshold): array {
            $aggregate = $this->aggregate($collective['type'], $collective['id'], $threshold);

            return [
                'subject' => $collective,
                'viewer_role' => 'member',
                'aggregate' => $aggregate,
            ];
        }, $this->resolver->collectivesForUser($user));
    }
}
