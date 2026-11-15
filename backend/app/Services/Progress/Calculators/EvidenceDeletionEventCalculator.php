<?php

namespace App\Services\Progress\Calculators;

use App\Models\EvidenceDeletionEvent;
use Carbon\CarbonImmutable;

/**
 * T014.10 — pure helpers for the minimal deletion event (política-retencion
 * §5.1/§5.3, plan §9). No database, clock or randomness: the caller supplies
 * `now` and the random actor pseudonym.
 */
final class EvidenceDeletionEventCalculator
{
    /**
     * Normalized retention in months, or null when retention is disabled
     * (null, '', 0, negative or non-numeric configuration).
     */
    public static function retentionMonths(mixed $configured): ?int
    {
        if ($configured === null || $configured === '' || ! is_numeric($configured)) {
            return null;
        }

        $months = (int) $configured;

        if ((float) $configured != $months || $months <= 0) {
            return null;
        }

        return $months;
    }

    /**
     * Retention deadline for an event that occurred at the given instant,
     * clamped to the end of the month instead of overflowing.
     */
    public static function expiresAt(CarbonImmutable $occurredAt, int $months): CarbonImmutable
    {
        return $occurredAt->addMonthsNoOverflow($months);
    }

    /**
     * The minimal event payload for one deleted source link. Reads ONLY the
     * whitelisted discriminator fields from $link — any extra keys (source
     * ids, answers, scores, results, levels, actor references) are ignored
     * and never copied (RF privacy boundary of the retention policy).
     *
     * @param  array{source_type:string, competency_type:string, competency_key:string}  $link
     * @return array{subject_id:string, source_type:string, competency_type:string, competency_key:string, change_type:string, actor_pseudonym:string, occurred_at:CarbonImmutable, expires_at:CarbonImmutable}
     */
    public static function minimalEvent(
        array $link,
        string $subjectId,
        CarbonImmutable $now,
        int $months,
        string $pseudonym,
    ): array {
        return [
            'subject_id' => $subjectId,
            'source_type' => $link['source_type'],
            'competency_type' => $link['competency_type'],
            'competency_key' => $link['competency_key'],
            'change_type' => EvidenceDeletionEvent::CHANGE_SOURCE_DELETED,
            'actor_pseudonym' => $pseudonym,
            'occurred_at' => $now,
            'expires_at' => self::expiresAt($now, $months),
        ];
    }
}
