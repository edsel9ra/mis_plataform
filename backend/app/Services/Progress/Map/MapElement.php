<?php

namespace App\Services\Progress\Map;

/**
 * Element explicitly linked to a map module. `sourceStatus` is the raw
 * status recorded on the source entity — the map never derives it (RF-013.3).
 */
final readonly class MapElement
{
    public function __construct(
        public MapElementType $type,
        public string $id,
        public string $title,
        public string $sourceStatus,
    ) {}
}
