<?php

namespace App\Services\Progress\Map;

/**
 * Collective rate for a single path identity + current definition version.
 * Same path with a different `versionKey` aggregates separately (RF-013.6).
 */
final readonly class PathAggregateRate
{
    public function __construct(
        public string $pathId,
        public string $versionKey,
        public string|array $title,
        public AggregateMetric $metric,
    ) {}
}
