<?php

namespace App\Services\Progress\Map;

use App\Services\Progress\Support\SuppressionReason;

/**
 * One collective metric: either a calculable mean ('value') or suppressed
 * when it has fewer than 3 calculable contributions. A suppressed metric
 * carries no value, count or base size (RF-013.7/.8).
 */
final readonly class AggregateMetric
{
    public const STATE_VALUE = 'value';
    public const STATE_SUPPRESSED = 'suppressed';

    public function __construct(
        public string $state,
        public int|float|null $value,
        public ?SuppressionReason $suppressionReason,
    ) {}
}
