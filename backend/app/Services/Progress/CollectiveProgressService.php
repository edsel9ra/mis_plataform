<?php

namespace App\Services\Progress;

use App\Services\Progress\Calculators\CollectiveAggregateCalculator;

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
