<?php

namespace App\Services\Progress\Calculators;

use App\Services\Progress\Support\MetricValue;

/**
 * Challenge→module rates (RF-012.1–.5, RF-012.11).
 *
 * Pure: operates on plain arrays already loaded by the caller (`['status' => ?string]`),
 * no DB, no clock, no Eloquent.
 *
 * Excluded statuses (template/canceled/abandoned) never count nor block.
 * Any non-excluded challenge with a null/unknown status marks the metric
 * `incomplete` and produces no partial rate.
 */
final class ChallengeModuleCalculator
{
    public const EXCLUDED_STATUSES = ['template', 'canceled', 'abandoned'];

    public const ELIGIBLE_STATUSES = ['assigned', 'in_progress', 'completed'];

    public const SOURCE = 'challenge_status';

    public const MODULE_INCOMPLETE = 'incomplete';

    public const MODULE_NO_DATA = 'no_data';

    public const MODULE_COMPLETED = 'completed';

    public const MODULE_NOT_COMPLETED = 'not_completed';

    /**
     * completed(eligible) / eligible * 100; `incomplete` on unknown status,
     * `no_data` when no eligible challenge exists.
     *
     * @param  array<int, array<string, mixed>>  $challenges
     */
    public function activityRate(array $challenges): MetricValue
    {
        [$eligible, $completed, $unknown] = $this->classify($challenges);

        if ($unknown) {
            return MetricValue::incomplete(null, self::SOURCE);
        }

        return MetricValue::make($eligible, MetricValue::percent($completed, $eligible), [
            'numerator' => $completed,
            'denominator' => $eligible,
            'source' => self::SOURCE,
        ]);
    }

    /**
     * Module completion state (RF-012.2): `incomplete` on unknown status,
     * `no_data` without eligible challenges, `completed` only when every
     * eligible challenge is completed, `not_completed` otherwise.
     *
     * @param  array<int, array<string, mixed>>  $challenges
     * @return string one of MODULE_* constants
     */
    public function moduleCompletion(array $challenges): string
    {
        [$eligible, $completed, $unknown] = $this->classify($challenges);

        if ($unknown) {
            return self::MODULE_INCOMPLETE;
        }

        if ($eligible === 0) {
            return self::MODULE_NO_DATA;
        }

        return $completed === $eligible
            ? self::MODULE_COMPLETED
            : self::MODULE_NOT_COMPLETED;
    }

    /**
     * @param  array<int, array<string, mixed>>  $challenges
     * @return array{0: int, 1: int, 2: bool} eligible, completed, unknown-found
     */
    private function classify(array $challenges): array
    {
        $eligible = 0;
        $completed = 0;
        $unknown = false;

        foreach ($challenges as $challenge) {
            $status = $challenge['status'] ?? null;

            if (in_array($status, self::EXCLUDED_STATUSES, true)) {
                continue;
            }

            if (! in_array($status, self::ELIGIBLE_STATUSES, true)) {
                $unknown = true;

                continue;
            }

            $eligible++;

            if ($status === 'completed') {
                $completed++;
            }
        }

        return [$eligible, $completed, $unknown];
    }
}
