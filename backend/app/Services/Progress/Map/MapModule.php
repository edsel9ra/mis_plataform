<?php

namespace App\Services\Progress\Map;

use App\Services\Progress\Support\MetricValue;

/**
 * Module in the learning map: the Spec 012 metric parts plus the elements
 * explicitly linked to it.
 */
final readonly class MapModule
{
    /**
     * @param  array<int, MapElement>  $elements
     */
    public function __construct(
        public string $id,
        public string $title,
        public int $order,
        public MetricValue $activityRate,
        public MapModuleCompletion $completion,
        public array $elements = [],
    ) {}
}
