<?php

namespace App\Services\Progress\Calculators;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use InvalidArgumentException;

/**
 * T015.1 — pure helpers for the unified progress history (Spec 015 plan §2,
 * RF-015.1/.7/.8). No database, facades, clock or randomness: adapters supply
 * normalized records and this class only normalizes, orders and paginates.
 *
 * A normalized event is an array with exactly these keys, in this order:
 * id, type, domain ('objectives'|'activities'|'competencies'),
 * occurred_at (?CarbonImmutable — null means "fecha no disponible", never
 * backfilled), actor (?array — {id,name} | {kind:"system"} | {kind:"hidden"}),
 * subject_ref, before, after and incomplete[] (fields the source cannot prove).
 */
final class ProgressHistoryCalculator
{
    public const DOMAINS = ['objectives', 'activities', 'competencies'];

    private const SORT_KEY_FORMAT = 'Y-m-d\TH:i:s.u\Z';

    /**
     * Builds a normalized event. A null occurred_at or actor is reported in
     * incomplete[] (RF-015.7/.8) instead of being reconstructed; the caller's
     * incomplete list is merged, de-duplicated and sorted.
     */
    public static function event(
        string $id,
        string $type,
        string $domain,
        ?CarbonImmutable $occurredAt,
        ?array $actor,
        array $subjectRef = [],
        ?array $before = null,
        ?array $after = null,
        array $incomplete = [],
    ): array {
        if ($id === '') {
            throw new InvalidArgumentException('History event id must not be empty.');
        }

        if ($type === '') {
            throw new InvalidArgumentException('History event type must not be empty.');
        }

        if (! in_array($domain, self::DOMAINS, true)) {
            throw new InvalidArgumentException("Unknown history domain [{$domain}].");
        }

        if ($occurredAt === null) {
            $incomplete[] = 'occurred_at';
        }

        if ($actor === null) {
            $incomplete[] = 'actor';
        }

        $incomplete = array_values(array_unique($incomplete));
        sort($incomplete);

        return [
            'id' => $id,
            'type' => $type,
            'domain' => $domain,
            'occurred_at' => $occurredAt,
            'actor' => $actor,
            'subject_ref' => $subjectRef,
            'before' => $before,
            'after' => $after,
            'incomplete' => $incomplete,
        ];
    }

    /**
     * First usable date candidate in priority order: CarbonInterface →
     * immutable, non-empty parseable string → parsed, anything else skipped.
     * Returns null when nothing usable remains — never "now" (RF-015.7/.8).
     */
    public static function occurredAt(mixed ...$candidates): ?CarbonImmutable
    {
        foreach ($candidates as $candidate) {
            if ($candidate instanceof CarbonInterface) {
                return $candidate->toImmutable();
            }

            if (is_string($candidate) && $candidate !== '') {
                try {
                    return CarbonImmutable::parse($candidate);
                } catch (InvalidFormatException) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Keeps only $allowed keys in $allowed order; extra keys are dropped.
     * Returns [filtered, missing] where missing lists the allowed keys that
     * are absent from $data or hold a null value.
     *
     * @return array{0: array, 1: list<string>}
     */
    public static function whitelist(array $data, array $allowed): array
    {
        $filtered = [];
        $missing = [];

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                $missing[] = $key;

                if (array_key_exists($key, $data)) {
                    $filtered[$key] = null;
                }

                continue;
            }

            $filtered[$key] = $data[$key];
        }

        return [$filtered, $missing];
    }

    /**
     * Total, stable sort key: [utcIso, id] where utcIso is the UTC instant
     * with microsecond precision (RF-015.7). Undated events have no sort key.
     *
     * @return array{0: string, 1: string}
     */
    public static function sortKey(array $event): array
    {
        $occurredAt = $event['occurred_at'] ?? null;

        if (! $occurredAt instanceof CarbonInterface) {
            throw new InvalidArgumentException('Cannot sort-key an undated history event.');
        }

        return [$occurredAt->utc()->format(self::SORT_KEY_FORMAT), (string) $event['id']];
    }

    /**
     * Ordering for dated events: occurred_at DESC, then id DESC.
     * Returns < 0 when $a comes first.
     */
    public static function compare(array $a, array $b): int
    {
        [$aIso, $aId] = self::sortKey($a);
        [$bIso, $bId] = self::sortKey($b);

        if ($aIso === $bIso) {
            return $bId <=> $aId;
        }

        return $bIso <=> $aIso;
    }

    /**
     * Splits events into dated (sorted by compare()) and undated (sorted by
     * id ASC). Undated events stay in their own group — they are never
     * interleaved and never assigned a date (RF-015.7).
     *
     * @return array{dated: list<array>, undated: list<array>}
     */
    public static function partition(array $events): array
    {
        $dated = [];
        $undated = [];

        foreach ($events as $event) {
            if (($event['occurred_at'] ?? null) instanceof CarbonInterface) {
                $dated[] = $event;
            } else {
                $undated[] = $event;
            }
        }

        usort($dated, fn (array $a, array $b): int => self::compare($a, $b));
        usort($undated, fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']));

        return ['dated' => $dated, 'undated' => $undated];
    }

    /**
     * Opaque cursor: base64url (no padding) of the JSON [utcIso, id] key.
     * The cursor never grants access — the subject is resolved server-side.
     */
    public static function encodeCursor(array $key): string
    {
        return rtrim(strtr(base64_encode(json_encode([$key[0], $key[1]])), '+/', '-_'), '=');
    }

    /**
     * Inverse of encodeCursor. Returns null for anything malformed: bad
     * base64, bad JSON, not a 2-element list of non-empty strings, or a
     * utcIso outside the canonical UTC format sortKey() emits.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function decodeCursor(string $cursor): ?array
    {
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        $key = json_decode($decoded, true);

        if (! is_array($key) || ! array_is_list($key) || count($key) !== 2) {
            return null;
        }

        [$iso, $id] = $key;

        if (! is_string($iso) || $iso === '' || ! is_string($id) || $id === '') {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat(self::SORT_KEY_FORMAT, $iso, 'UTC');
        } catch (InvalidFormatException) {
            return null;
        }

        if ($parsed === false || $parsed->format(self::SORT_KEY_FORMAT) !== $iso) {
            return null;
        }

        return [$iso, $id];
    }

    /**
     * K-way merge of dated feeds into one page (plan §2 mergePage).
     *
     * Keeps only events strictly after the $after cursor key in compare()
     * order, de-duplicates by id (first occurrence wins), sorts defensively
     * and takes $limit. next_cursor is set only when events remain beyond
     * the page.
     *
     * @param  array<int, list<array>>  $feeds
     * @param  array{0: string, 1: string}|null  $after  decoded cursor key
     * @return array{data: list<array>, next_cursor: ?string}
     */
    public static function mergePage(array $feeds, ?array $after, int $limit): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('History page limit must be at least 1.');
        }

        $cursorEvent = $after !== null
            ? ['occurred_at' => CarbonImmutable::parse($after[0]), 'id' => $after[1]]
            : null;

        $all = [];
        $seen = [];

        foreach ($feeds as $feed) {
            foreach ($feed as $event) {
                if (! ($event['occurred_at'] ?? null) instanceof CarbonInterface) {
                    throw new InvalidArgumentException('mergePage feeds must contain only dated events.');
                }

                if ($cursorEvent !== null && self::compare($event, $cursorEvent) <= 0) {
                    continue;
                }

                if (isset($seen[$event['id']])) {
                    continue;
                }

                $seen[$event['id']] = true;
                $all[] = $event;
            }
        }

        usort($all, fn (array $a, array $b): int => self::compare($a, $b));

        $page = array_slice($all, 0, $limit);

        return [
            'data' => $page,
            'next_cursor' => count($all) > $limit
                ? self::encodeCursor(self::sortKey(end($page)))
                : null,
        ];
    }
}
