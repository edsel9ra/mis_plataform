<?php

namespace App\Services\Progress\Map;

use App\Services\Progress\Support\MetricValue;

/**
 * Learning path in the individual map. `versionKey` identifies the current
 * persisted definition for comparable aggregation (T013.3 fills it); it is
 * not a historical snapshot.
 */
final readonly class MapPath
{
    /**
     * @param  array<int, MapModule>  $modules
     * @param  string|array<string, string>  $title  i18n map like the source path
     */
    public function __construct(
        public string $id,
        public string|array $title,
        public string $versionKey,
        public MetricValue $rate,
        public array $modules = [],
    ) {}
}
