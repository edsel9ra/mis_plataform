<?php

namespace App\Services\Progress\Calculators;

/**
 * F8 — pure competency metrics (RF-7).
 *
 * Skills and OCEAN are always kept as distinguishable sources (P9/P15).
 * OCEAN can be `blocked` (no entitlement) or `no_data` (not completed); a
 * missing assessment must never surface as zeros (CA-7.4).
 */
final class CompetencyMetricsCalculator
{
    /**
     * @param  array<int, array<string, mixed>>  $skills
     * @param  array<string, mixed>|null  $ocean
     * @return array{state: string, skills: array<int, array<string, mixed>>, ocean: array<string, mixed>}
     */
    public function build(array $skills, ?array $ocean, bool $oceanEntitled): array
    {
        $mappedSkills = [];

        foreach ($skills as $skill) {
            $mappedSkills[] = [
                'id' => $skill['id'] ?? null,
                'tag' => $skill['tag'] ?? null,
                'category' => $skill['category'] ?? null,
                'level' => $skill['level'] ?? null,
                'source' => 'skills',
            ];
        }

        $oceanBlock = $this->resolveOcean($ocean, $oceanEntitled);

        return [
            'state' => $this->resolveState($mappedSkills, $oceanBlock),
            'skills' => $mappedSkills,
            'ocean' => $oceanBlock,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $ocean
     * @return array<string, mixed>
     */
    private function resolveOcean(?array $ocean, bool $entitled): array
    {
        if (! $entitled) {
            return [
                'state' => 'blocked',
                'reason' => 'entitlement_missing',
                'source' => 'personality',
            ];
        }

        if ($ocean === null || ($ocean['completed_at'] ?? null) === null) {
            return [
                'state' => 'no_data',
                'source' => 'personality',
            ];
        }

        return [
            'state' => 'value',
            'factors' => $ocean['factors'] ?? [],
            'assessed_at' => $ocean['completed_at'],
            'test_version' => $ocean['test_version'] ?? null,
            'source' => 'personality',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $skills
     * @param  array<string, mixed>  $ocean
     */
    private function resolveState(array $skills, array $ocean): string
    {
        if ($skills !== []) {
            return 'value';
        }

        return match ($ocean['state'] ?? 'no_data') {
            'value' => 'value',
            'blocked' => 'blocked',
            default => 'no_data',
        };
    }
}
