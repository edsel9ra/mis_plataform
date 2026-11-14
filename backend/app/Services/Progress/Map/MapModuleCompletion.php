<?php

namespace App\Services\Progress\Map;

/**
 * Verifiable module completion state (RF-17/Spec 012): state from the
 * calculator plus the persisted completion timestamp, if any.
 */
final readonly class MapModuleCompletion
{
    public function __construct(
        public string $state,
        public ?string $completedAt,
    ) {}
}
