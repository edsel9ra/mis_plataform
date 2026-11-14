<?php

namespace App\Services\Progress\Map;

use App\Services\Progress\Support\SuppressionReason;

/**
 * Collective learning aggregate block (RF-013.5-.8). When the eligible base
 * is below the shared threshold the whole block is suppressed and carries
 * no metrics; otherwise each metric is suppressed independently.
 */
final readonly class CollectiveLearningAggregate
{
    public const STATE_AVAILABLE = 'available';
    public const STATE_SUPPRESSED = 'suppressed';

    /**
     * @param  array<int, PathAggregateRate>  $rates
     */
    public function __construct(
        public string $state,
        public ?SuppressionReason $suppressionReason,
        public ?AggregateMetric $activityRate,
        public array $rates = [],
    ) {}
}
