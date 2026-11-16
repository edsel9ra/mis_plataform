<?php

namespace App\Services\Progress\History;

use App\Models\User;
use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use InvalidArgumentException;

/**
 * T015.6 — unified history page (RF-015.1/.7/.8, plan §4 steps 2–4). Merges
 * every adapter feed through `ProgressHistoryCalculator::mergePage` into a
 * single cursor-paginated, newest-first timeline, and attaches the undated
 * annex (capped, deduplicated, id-sorted) on the first page only.
 */
final class ProgressHistoryService
{
    public const DEFAULT_LIMIT = 25;

    public const MAX_LIMIT = 50;

    public const UNDATED_CAP = 50;

    /** @param list<HistoryAdapter> $adapters */
    public function __construct(private array $adapters) {}

    /**
     * @return array{data: list<array>, undated: list<array>, next_cursor: ?string}
     */
    public function page(User $subject, ?string $cursor, int $limit = self::DEFAULT_LIMIT): array
    {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new InvalidArgumentException("History limit must be between 1 and ".self::MAX_LIMIT);
        }

        $after = null;
        if ($cursor !== null) {
            $after = ProgressHistoryCalculator::decodeCursor($cursor);
            if ($after === null) {
                throw new InvalidArgumentException('Invalid history cursor');
            }
        }

        $page = ProgressHistoryCalculator::mergePage(
            array_map(
                fn (HistoryAdapter $adapter): array => $adapter->dated($subject, $after, $limit),
                $this->adapters,
            ),
            $after,
            $limit,
        );

        return [
            'data' => $page['data'],
            'undated' => $cursor === null ? $this->undated($subject) : [],
            'next_cursor' => $page['next_cursor'],
        ];
    }

    /**
     * @return list<array>
     */
    private function undated(User $subject): array
    {
        $collected = [];
        foreach ($this->adapters as $adapter) {
            $collected = array_merge($collected, $adapter->undated($subject, self::UNDATED_CAP));
        }

        return collect(ProgressHistoryCalculator::partition($collected)['undated'])
            ->unique('id')
            ->take(self::UNDATED_CAP)
            ->values()
            ->all();
    }
}
