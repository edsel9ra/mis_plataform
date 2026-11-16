<?php

namespace App\Services\Progress\History;

use App\Models\User;

/**
 * T015.2 — shared adapter contract for the unified progress history
 * (Spec 015 plan §2, RF-015.1/.7/.8). Each adapter reads ONE source of
 * verified records and projects it to normalized events built with
 * ProgressHistoryCalculator::event(). Read-only: adapters never write.
 */
interface HistoryAdapter
{
    /**
     * Dated events of $subject strictly after $after (decoded cursor key
     * [utcIso, eventId] or null), ordered exactly like
     * ProgressHistoryCalculator::compare(), at most $limit + 1.
     *
     * @param  array{0: string, 1: string}|null  $after
     * @return list<array>
     */
    public function dated(User $subject, ?array $after, int $limit): array;

    /**
     * Undated events of $subject (group «fecha no disponible», RF-015.7),
     * at most $cap, ordered by event id ASC.
     *
     * @return list<array>
     */
    public function undated(User $subject, int $cap): array;
}
