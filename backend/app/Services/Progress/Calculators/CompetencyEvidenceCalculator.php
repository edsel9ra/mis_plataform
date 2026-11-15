<?php

namespace App\Services\Progress\Calculators;

/**
 * T014.3 — pure evidence/completeness classifier (RF-014.1–.3, .5–.6).
 *
 * Evidence exists only through an explicit link row. Its state derives
 * solely from the linked source's `completed_at`: completed → `current`,
 * uncompleted → `incomplete`, missing row → `missing_source` (the link is
 * retained historically but is never presented as current evidence).
 * A competency with no link produces no entry — nothing is ever inferred.
 *
 * Operates on normalized arrays only: no database, network or clock, and
 * it never writes a competency level (`UserSkill.level` stays untouched).
 * Source payloads are minimized to contextual fields (instrument /
 * test_version / completed_at); answers, scores and raw OCEAN payloads
 * never pass through.
 */
final class CompetencyEvidenceCalculator
{
    public const STATE_CURRENT = 'current';

    public const STATE_INCOMPLETE = 'incomplete';

    public const STATE_MISSING_SOURCE = 'missing_source';

    public const STATE_BLOCKED = 'blocked';

    private const COMPETENCY_OCEAN = 'ocean_factor';

    /**
     * Classify one link given its source row (or null when the source no
     * longer exists). Deletion handling is the retention-gated capability;
     * here a missing source simply means no active evidence.
     *
     * @param  array{completed_at: ?string}|null  $source
     */
    public static function evidenceState(?array $source): string
    {
        if ($source === null) {
            return self::STATE_MISSING_SOURCE;
        }

        return ($source['completed_at'] ?? null) !== null
            ? self::STATE_CURRENT
            : self::STATE_INCOMPLETE;
    }

    /**
     * Build the evidence map for a set of explicit links.
     *
     * @param  array<int, array{id: string, source_type: string, source_id: string, competency_type: string, competency_key: string}>  $links
     * @param  array<string, array<string, array{completed_at: ?string, instrument?: mixed, test_version?: ?string}>>  $sources
     *         map: source_type ⇒ (source_id ⇒ minimized source row)
     * @return array<string, array<int, array<string, mixed>>>
     *         keyed "{competency_type}:{competency_key}" ⇒ ordered entries
     */
    public static function build(array $links, array $sources, bool $oceanEntitled): array
    {
        $evidence = [];

        foreach ($links as $link) {
            $key = $link['competency_type'].':'.$link['competency_key'];

            if ($link['competency_type'] === self::COMPETENCY_OCEAN && ! $oceanEntitled) {
                // Blocked under the existing OCEAN entitlement policy — the
                // link is acknowledged but no source detail is exposed.
                $evidence[$key][] = [
                    'link_id' => $link['id'],
                    'state' => self::STATE_BLOCKED,
                    'reason' => 'entitlement_missing',
                ];

                continue;
            }

            $source = $sources[$link['source_type']][$link['source_id']] ?? null;
            $state = self::evidenceState($source);

            $evidence[$key][] = [
                'link_id' => $link['id'],
                'state' => $state,
                'source_type' => $link['source_type'],
                'source_id' => $link['source_id'],
                'instrument' => $source['instrument'] ?? null,
                'test_version' => $source['test_version'] ?? null,
                'completed_at' => $state === self::STATE_CURRENT ? $source['completed_at'] : null,
            ];
        }

        return $evidence;
    }
}
