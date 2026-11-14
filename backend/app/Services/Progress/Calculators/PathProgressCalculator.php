<?php

namespace App\Services\Progress\Calculators;

use App\Services\Progress\Support\MetricValue;

/**
 * Path progress rate (RF-012.3–.6, RF-012.12).
 *
 * Pure: operates on plain arrays `['completion' => <ChallengeModuleCalculator::MODULE_*>,
 * 'completed_record' => bool]`, no DB, no clock.
 *
 * `completed_record` is the persisted individual module completion
 * (`user_module_completions` row, RF-012.2); T012.6 keeps it consistent with
 * the `completion` rule result — the numerator trusts the record, not the
 * derived rule, so a stale record is visible instead of silently re-derived.
 */
final class PathProgressCalculator
{
    public const SOURCE = 'module_completion';

    /**
     * eligible-with-completed-record / eligible * 100; `incomplete` when any
     * module is incomplete (no partial rate), `no_data` without eligible
     * modules (completion !== MODULE_NO_DATA).
     *
     * @param  array<int, array{completion?: ?string, completed_record?: bool}>  $modules
     */
    public function pathRate(array $modules): MetricValue
    {
        $eligible = 0;
        $completed = 0;

        foreach ($modules as $module) {
            $completion = $module['completion'] ?? null;

            if ($completion === ChallengeModuleCalculator::MODULE_INCOMPLETE) {
                return MetricValue::incomplete(null, self::SOURCE);
            }

            if ($completion === ChallengeModuleCalculator::MODULE_NO_DATA) {
                continue;
            }

            $eligible++;

            if (($module['completed_record'] ?? false) === true) {
                $completed++;
            }
        }

        return MetricValue::make($eligible, MetricValue::percent($completed, $eligible), [
            'numerator' => $completed,
            'denominator' => $eligible,
            'source' => self::SOURCE,
        ]);
    }
}
