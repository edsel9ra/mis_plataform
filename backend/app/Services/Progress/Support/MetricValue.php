<?php

namespace App\Services\Progress\Support;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Normalized indicator value (plan §9.0).
 *
 * Distinguishes "no data" (source has no records) from a real zero, and marks
 * partial data as "incomplete" (RF-13). Pure value object: no IO, no clock.
 */
final class MetricValue implements Arrayable, JsonSerializable
{
    public const VALUE = 'value';
    public const ZERO = 'zero';
    public const NO_DATA = 'no_data';
    public const INCOMPLETE = 'incomplete';
    public const SUPPRESSED = 'suppressed';
    public const BLOCKED = 'blocked';

    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly string $state,
        public readonly int|float|null $value = null,
        public readonly int|float|null $numerator = null,
        public readonly int|float|null $denominator = null,
        public readonly ?string $source = null,
        public readonly ?array $period = null,
        public readonly bool $incomplete = false,
        public readonly array $extra = [],
    ) {}

    /**
     * F12 — resolve the state from a record count and the computed value.
     * "no_data" !== "0": zero is only reported when records actually exist.
     */
    public static function stateFor(int $records, int|float|null $computed, bool $incomplete = false): string
    {
        if ($incomplete) {
            return self::INCOMPLETE;
        }

        if ($records === 0) {
            return self::NO_DATA;
        }

        if ($computed === null || (float) $computed === 0.0) {
            return self::ZERO;
        }

        return self::VALUE;
    }

    /**
     * F14 — percentage, or null when there is no data (denominator = 0).
     */
    public static function percent(int|float|null $numerator, int|float|null $denominator): ?int
    {
        if ($denominator === null || (float) $denominator === 0.0) {
            return null;
        }

        return (int) round(((float) ($numerator ?? 0) / (float) $denominator) * 100);
    }

    /**
     * Build a metric from a record count and a computed value.
     *
     * @param  array{numerator?: int|float|null, denominator?: int|float|null, source?: string|null, period?: array|null, incomplete?: bool, extra?: array<string, mixed>}  $options
     */
    public static function make(int $records, int|float|null $computed, array $options = []): self
    {
        $incomplete = (bool) ($options['incomplete'] ?? false);
        $state = self::stateFor($records, $computed, $incomplete);

        return new self(
            state: $state,
            value: $state === self::NO_DATA ? null : $computed,
            numerator: $options['numerator'] ?? null,
            denominator: $options['denominator'] ?? null,
            source: $options['source'] ?? null,
            period: $options['period'] ?? null,
            incomplete: $incomplete,
            extra: $options['extra'] ?? [],
        );
    }

    public static function noData(?string $source = null, ?array $period = null): self
    {
        return new self(self::NO_DATA, null, null, null, $source, $period);
    }

    public static function suppressed(?array $period = null): self
    {
        return new self(self::SUPPRESSED, null, null, null, null, $period);
    }

    public static function incomplete(int|float|null $computed, ?string $source = null, ?array $period = null): self
    {
        return new self(self::INCOMPLETE, $computed, null, null, $source, $period, true);
    }

    public static function value(int|float $computed, ?string $source = null, ?array $period = null): self
    {
        return new self($computed == 0 ? self::ZERO : self::VALUE, $computed, null, null, $source, $period);
    }

    public function isSuppressed(): bool
    {
        return $this->state === self::SUPPRESSED;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge([
            'state' => $this->state,
            'value' => $this->value,
            'numerator' => $this->numerator,
            'denominator' => $this->denominator,
            'source' => $this->source,
            'period' => $this->period,
            'incomplete' => $this->incomplete,
        ], $this->extra);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
