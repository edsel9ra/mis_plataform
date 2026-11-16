<?php

namespace App\Services\Progress\History;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

/**
 * T015.2 — makes SQL ordering identical to
 * ProgressHistoryCalculator::compare() so LIMIT never truncates wrongly
 * (Spec 015 plan §2: cursor keyset pagination, no offsets).
 *
 * $instantSql must yield a timestamptz (e.g.
 * "(activity_log.created_at AT TIME ZONE ?)" with its timezone bindings in
 * $instantBindings). $idSql must yield the FULL event id string exactly as
 * the adapter builds it in PHP — e.g.
 * "('objective_activity:' || lpad(activity_log.id::text, 20, '0'))".
 * Numeric ids are left-padded to 20 digits BOTH here and in PHP so string
 * order equals numeric order; COLLATE "C" makes Postgres string order equal
 * PHP's byte-wise <=>. UUIDs are used as-is (lowercase).
 *
 * The cursor filter keeps events strictly AFTER the key in compare()
 * order (older instants, or same instant with a smaller id):
 *   instant < utcIso OR (instant = utcIso AND id < cursorId)
 */
final class HistoryCursorQuery
{
    /**
     * @param  array{0: string, 1: string}|null  $after
     */
    public static function apply(
        Builder|EloquentBuilder $query,
        string $instantSql,
        array $instantBindings,
        string $idSql,
        ?array $after,
        int $limit,
    ): Builder|EloquentBuilder {
        if ($after !== null) {
            $query->whereRaw(
                "(({$instantSql}) < ?::timestamptz OR (({$instantSql}) = ?::timestamptz AND ({$idSql}) COLLATE \"C\" < ?))",
                [...$instantBindings, $after[0], ...$instantBindings, $after[0], $after[1]],
            );
        }

        $query->orderByRaw("({$instantSql}) DESC", $instantBindings);
        $query->orderByRaw("({$idSql}) COLLATE \"C\" DESC");
        $query->limit($limit + 1);

        return $query;
    }
}
