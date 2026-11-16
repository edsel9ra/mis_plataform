<?php

use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * T015.1 — pure normalization, date, ordering, cursor and merge helpers for
 * the unified progress history (Spec 015 plan §2, RF-015.1/.7/.8).
 * No database, clock or inference.
 */

function phEvent(string $id, ?CarbonImmutable $occurredAt): array
{
    return ProgressHistoryCalculator::event(
        $id,
        'objective.status_changed',
        'objectives',
        $occurredAt,
        ['id' => 'u1', 'name' => 'Ana'],
        ['objective_id' => 'o1'],
        ['status' => 'draft'],
        ['status' => 'in_progress'],
    );
}

function phEnc(string $json): string
{
    return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
}

it('builds events with the exact key order', function (): void {
    $event = ProgressHistoryCalculator::event(
        'e1',
        'objective.status_changed',
        'objectives',
        CarbonImmutable::parse('2026-11-15T10:00:00Z'),
        ['id' => 'u1', 'name' => 'Ana'],
        ['objective_id' => 'o1'],
        ['status' => 'draft'],
        ['status' => 'in_progress'],
    );

    expect(array_keys($event))->toBe([
        'id', 'type', 'domain', 'occurred_at', 'actor', 'subject_ref', 'before', 'after', 'incomplete',
    ]);
    expect($event['incomplete'])->toBe([]);
});

it('marks a missing date and a missing actor as incomplete, merged unique and sorted', function (): void {
    $event = ProgressHistoryCalculator::event(
        'e1', 'challenge.status_transition', 'activities', null, null, [], null, null, ['zzz', 'aaa', 'aaa'],
    );

    expect($event['occurred_at'])->toBeNull();
    expect($event['actor'])->toBeNull();
    expect($event['incomplete'])->toBe(['aaa', 'actor', 'occurred_at', 'zzz']);
});

it('rejects an unknown domain', function (): void {
    ProgressHistoryCalculator::event('e1', 't', 'reports', null, null);
})->throws(InvalidArgumentException::class);

it('rejects an empty id', function (): void {
    ProgressHistoryCalculator::event('', 't', 'objectives', null, null);
})->throws(InvalidArgumentException::class);

it('rejects an empty type', function (): void {
    ProgressHistoryCalculator::event('e1', '', 'objectives', null, null);
})->throws(InvalidArgumentException::class);

it('accepts every declared domain', function (): void {
    foreach (ProgressHistoryCalculator::DOMAINS as $domain) {
        expect(ProgressHistoryCalculator::event('e', 't', $domain, null, null)['domain'])->toBe($domain);
    }
});

it('returns the first usable occurred_at candidate in priority order', function (): void {
    $first = CarbonImmutable::parse('2026-11-15T10:00:00Z');

    $result = ProgressHistoryCalculator::occurredAt(null, '', 'not-a-date', $first, '2030-01-01T00:00:00Z');

    expect($result)->toBeInstanceOf(CarbonImmutable::class);
    expect($result->utc()->format('Y-m-d\TH:i:s\Z'))->toBe('2026-11-15T10:00:00Z');
});

it('normalizes offset strings to the same instant', function (): void {
    $offset = ProgressHistoryCalculator::occurredAt('2026-11-15T10:00:00-06:00');
    $utc = ProgressHistoryCalculator::occurredAt('2026-11-15T16:00:00Z');

    expect($offset->eq($utc))->toBeTrue();
});

it('converts mutable Carbon instances to immutable', function (): void {
    $result = ProgressHistoryCalculator::occurredAt(Carbon::parse('2026-11-15T10:00:00Z'));

    expect($result)->toBeInstanceOf(CarbonImmutable::class);
    expect($result->format('H:i:s'))->toBe('10:00:00');
});

it('returns null when no candidate is usable or none are given', function (): void {
    expect(ProgressHistoryCalculator::occurredAt())->toBeNull();
    expect(ProgressHistoryCalculator::occurredAt(null, '', 'not-a-date'))->toBeNull();
});

it('keeps only allowed keys in allowed order and reports absent or null fields', function (): void {
    [$filtered, $missing] = ProgressHistoryCalculator::whitelist(
        [
            'note' => 'contenido privado',
            'answers' => [1, 2, 3],
            'actor_pseudonym' => 'pseudo',
            'id' => 'x',
            'status' => null,
            'reason' => 'r',
        ],
        ['id', 'status', 'reason', 'absent_key'],
    );

    expect(array_keys($filtered))->toBe(['id', 'status', 'reason']);
    expect($filtered['id'])->toBe('x');
    expect($filtered['status'])->toBeNull();
    expect($missing)->toBe(['status', 'absent_key']);
});

it('sort-keys dated events as [utcIso, id] and throws on undated events', function (): void {
    $event = phEvent('e1', CarbonImmutable::parse('2026-11-15T10:00:00-06:00'));

    expect(ProgressHistoryCalculator::sortKey($event))->toBe(['2026-11-15T16:00:00.000000Z', 'e1']);

    ProgressHistoryCalculator::sortKey(phEvent('e2', null));
})->throws(InvalidArgumentException::class);

it('orders by occurred_at DESC', function (): void {
    $older = phEvent('b', CarbonImmutable::parse('2026-11-15T10:00:00Z'));
    $newer = phEvent('a', CarbonImmutable::parse('2026-11-16T10:00:00Z'));

    expect(ProgressHistoryCalculator::compare($newer, $older))->toBeLessThan(0);
    expect(ProgressHistoryCalculator::compare($older, $newer))->toBeGreaterThan(0);
});

it('treats the same instant in different offsets as a tie resolved by id DESC', function (): void {
    $a = phEvent('a', CarbonImmutable::parse('2026-11-15T10:00:00-06:00')); // 16:00Z
    $b = phEvent('b', CarbonImmutable::parse('2026-11-15T16:00:00Z'));

    expect(ProgressHistoryCalculator::compare($b, $a))->toBeLessThan(0);
    expect(ProgressHistoryCalculator::compare($a, $b))->toBeGreaterThan(0);
});

it('respects microsecond differences', function (): void {
    $base = CarbonImmutable::parse('2026-11-15T16:00:00.000000Z');
    $later = $base->addMicrosecond();

    expect(ProgressHistoryCalculator::compare(phEvent('x', $later), phEvent('y', $base)))->toBeLessThan(0);
});

it('partitions dated and undated events without inventing dates', function (): void {
    $datedOlder = phEvent('d2', CarbonImmutable::parse('2026-11-14T10:00:00Z'));
    $datedNewer = phEvent('d1', CarbonImmutable::parse('2026-11-15T10:00:00Z'));
    $undatedB = phEvent('u2', null);
    $undatedA = phEvent('u1', null);

    $result = ProgressHistoryCalculator::partition([$undatedB, $datedOlder, $undatedA, $datedNewer]);

    expect(array_column($result['dated'], 'id'))->toBe(['d1', 'd2']);
    expect(array_column($result['undated'], 'id'))->toBe(['u1', 'u2']);
    expect($result['undated'][0]['occurred_at'])->toBeNull();
    expect($result['undated'][0]['incomplete'])->toContain('occurred_at');
});

it('round-trips an opaque cursor', function (): void {
    $key = ['2026-11-15T16:00:00.123456Z', 'ev:1'];

    expect(ProgressHistoryCalculator::decodeCursor(ProgressHistoryCalculator::encodeCursor($key)))->toBe($key);
});

it('rejects malformed cursors', function (string $cursor): void {
    expect(ProgressHistoryCalculator::decodeCursor($cursor))->toBeNull();
})->with([
    'not base64' => ['not base64 !!'],
    'not json' => [phEnc('not json')],
    'numeric entries' => [phEnc('[1,2]')],
    'single element' => [phEnc('["only-one"]')],
    'unparseable date' => [phEnc('["garbage-date","id"]')],
    'empty id' => [phEnc('["2026-11-15T16:00:00.000000Z",""]')],
    'empty string' => [''],
]);

it('rejects cursor dates outside the canonical sortKey format', function (string $iso): void {
    expect(ProgressHistoryCalculator::decodeCursor(phEnc(json_encode([$iso, 'id']))))->toBeNull();
})->with([
    'military timezone' => ['x'],
    'natural language' => ['tomorrow'],
    'date only' => ['2026-11-15'],
    'no microseconds' => ['2026-11-15T16:00:00Z'],
    'overflowing month' => ['2026-13-01T00:00:00.000000Z'],
]);

it('round-trips a canonical sortKey through the cursor', function (): void {
    $event = phEvent('ev:1', CarbonImmutable::parse('2026-11-15T10:00:00.123456-06:00'));
    $key = ProgressHistoryCalculator::sortKey($event);

    expect($key)->toBe(['2026-11-15T16:00:00.123456Z', 'ev:1']);
    expect(ProgressHistoryCalculator::decodeCursor(ProgressHistoryCalculator::encodeCursor($key)))->toBe($key);
});

it('merges unsorted feeds into one global order with stable tie-breaking', function (): void {
    $at = fn (string $hour) => CarbonImmutable::parse("2026-11-15T{$hour}:00:00Z");

    $feeds = [
        [phEvent('f1-b', $at('12')), phEvent('f1-a', $at('10'))],
        [phEvent('f2-b', $at('11')), phEvent('f2-a', $at('10'))],
        [phEvent('f3', $at('13'))],
    ];

    $page = ProgressHistoryCalculator::mergePage($feeds, null, 10);

    expect(array_column($page['data'], 'id'))->toBe(['f3', 'f1-b', 'f2-b', 'f2-a', 'f1-a']);
    expect($page['next_cursor'])->toBeNull();
});

it('de-duplicates by id keeping the first occurrence', function (): void {
    $first = phEvent('dup', CarbonImmutable::parse('2026-11-15T09:00:00Z'));
    $second = phEvent('dup', CarbonImmutable::parse('2026-11-15T11:00:00Z'));

    $page = ProgressHistoryCalculator::mergePage([[$first], [$second]], null, 10);

    expect($page['data'])->toHaveCount(1);
    expect($page['data'][0]['occurred_at']->format('H:i'))->toBe('09:00');
});

it('pages through all events exactly once in order', function (): void {
    $events = [
        phEvent('e0', CarbonImmutable::parse('2026-11-15T10:00:00Z')),
        phEvent('e1', CarbonImmutable::parse('2026-11-15T10:00:01Z')),
        phEvent('e2', CarbonImmutable::parse('2026-11-15T10:00:02Z')),
        phEvent('e3', CarbonImmutable::parse('2026-11-15T10:00:03Z')),
        phEvent('e4', CarbonImmutable::parse('2026-11-15T10:00:04Z')),
        phEvent('e5', CarbonImmutable::parse('2026-11-15T10:00:05Z')),
    ];
    $feeds = [[$events[2], $events[0]], [$events[4]], [$events[5], $events[3], $events[1]]];

    $seen = [];
    $after = null;
    $pages = 0;

    do {
        $page = ProgressHistoryCalculator::mergePage($feeds, $after, 2);
        $pages++;
        $seen = array_merge($seen, array_column($page['data'], 'id'));
        $after = $page['next_cursor'] !== null
            ? ProgressHistoryCalculator::decodeCursor($page['next_cursor'])
            : null;
    } while ($after !== null);

    expect($seen)->toBe(['e5', 'e4', 'e3', 'e2', 'e1', 'e0']);
    expect($seen)->toHaveCount(count(array_unique($seen)));
    expect($page['next_cursor'])->toBeNull();
    expect($pages)->toBe(3);
});

it('returns null next_cursor when the page consumes exactly the remaining events', function (): void {
    $feeds = [[
        phEvent('a', CarbonImmutable::parse('2026-11-15T11:00:00Z')),
        phEvent('b', CarbonImmutable::parse('2026-11-15T10:00:00Z')),
    ]];

    $page = ProgressHistoryCalculator::mergePage($feeds, null, 2);

    expect($page['data'])->toHaveCount(2);
    expect($page['next_cursor'])->toBeNull();
});

it('keeps only events strictly after the cursor', function (): void {
    $t = CarbonImmutable::parse('2026-11-15T10:00:00Z');
    $feeds = [[
        phEvent('a-tie', $t),
        phEvent('b-tie', $t),
        phEvent('older', CarbonImmutable::parse('2026-11-15T09:00:00Z')),
    ]];

    // Same instant as the events; id 'b-tie' sorts first (id DESC), so only 'older' follows it.
    $after = ProgressHistoryCalculator::decodeCursor(
        ProgressHistoryCalculator::encodeCursor(['2026-11-15T10:00:00.000000Z', 'b-tie'])
    );

    $page = ProgressHistoryCalculator::mergePage($feeds, $after, 10);

    expect(array_column($page['data'], 'id'))->toBe(['a-tie', 'older']);
});

it('rejects a limit below 1', function (): void {
    ProgressHistoryCalculator::mergePage([], null, 0);
})->throws(InvalidArgumentException::class);

it('rejects undated events inside a feed', function (): void {
    ProgressHistoryCalculator::mergePage([[phEvent('u1', null)]], null, 10);
})->throws(InvalidArgumentException::class);

it('does not mutate the input feeds', function (): void {
    $feeds = [[
        phEvent('b', CarbonImmutable::parse('2026-11-15T10:00:00Z')),
        phEvent('a', CarbonImmutable::parse('2026-11-15T11:00:00Z')),
    ]];
    $snapshot = serialize($feeds);

    ProgressHistoryCalculator::mergePage($feeds, null, 10);

    expect(serialize($feeds))->toBe($snapshot);
});
