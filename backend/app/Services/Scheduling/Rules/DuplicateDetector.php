<?php

namespace App\Services\Scheduling\Rules;

use DateTimeInterface;

final class DuplicateDetector
{
    public static function normalizeTitle(string $title): string
    {
        $collapsedWhitespace = preg_replace('/\s+/u', ' ', trim($title));

        return mb_strtolower(trim($collapsedWhitespace ?? $title), 'UTF-8');
    }

    /**
     * Returns the id of the matching existing session, if any.
     *
     * @param array{relationship_id: string, start: DateTimeInterface, duration_minutes: int, title: string} $candidate
     * @param list<array{id: string, relationship_id: string, start: DateTimeInterface, duration_minutes: int, title: string}> $existing
     */
    public static function isDuplicate(array $candidate, array $existing, DateTimeInterface $now): ?string
    {
        foreach ($existing as $session) {
            if ((string) $candidate['relationship_id'] !== (string) $session['relationship_id']) {
                continue;
            }

            if (self::instantKey($candidate['start']) !== self::instantKey($session['start'])) {
                continue;
            }

            if ((int) $candidate['duration_minutes'] !== (int) $session['duration_minutes']) {
                continue;
            }

            if (self::normalizeTitle($candidate['title']) !== self::normalizeTitle($session['title'])) {
                continue;
            }

            return (string) $session['id'];
        }

        return null;
    }

    private static function instantKey(DateTimeInterface $instant): string
    {
        return $instant->format('U.u');
    }
}
