<?php

namespace App\Services\Scheduling\Rules;

use DateTimeInterface;

final class OverlapDetector
{
    public static function overlaps(
        DateTimeInterface $startA,
        DateTimeInterface $endA,
        DateTimeInterface $startB,
        DateTimeInterface $endB,
    ): bool {
        return $startA < $endB && $startB < $endA;
    }

    /**
     * @param array{start: DateTimeInterface, end: DateTimeInterface, participants: list<string>} $candidate
     * @param list<array{id: string, start: DateTimeInterface, end: DateTimeInterface, status: string, participants: list<string>}> $existing
     * @return list<array{session_id: string, user_id: string}>
     */
    public static function conflicts(array $candidate, array $existing, DateTimeInterface $now): array
    {
        $conflicts = [];

        foreach ($existing as $session) {
            if (in_array($session['status'], ['canceled', 'not_completed'], true)) {
                continue;
            }

            if (!self::overlaps(
                $candidate['start'],
                $candidate['end'],
                $session['start'],
                $session['end'],
            )) {
                continue;
            }

            foreach (array_unique($candidate['participants']) as $participantId) {
                if (in_array($participantId, $session['participants'], true)) {
                    $conflicts[] = [
                        'session_id' => (string) $session['id'],
                        'user_id' => (string) $participantId,
                    ];
                }
            }
        }

        return $conflicts;
    }
}
