<?php

namespace App\Services\Progress\Calculators;

use App\Services\Progress\Map\MapElement;
use App\Services\Progress\Map\MapElementType;

/**
 * Learning map ordering and element placement (RF-013.1, .3, .4).
 *
 * Pure: operates on plain arrays already loaded by the caller, no DB, no
 * clock, no Eloquent.
 *
 * Modules keep only their current persisted order with a deterministic id
 * tie-break; the map adds no stages or groupings. An element lands under a
 * module only when its explicit `path_module_id` link points to a module of
 * the path — never inferred from titles or any other signal. The source
 * status is copied verbatim.
 */
final class LearningMapCalculator
{
    /**
     * Deterministic module order: `order` ASC, `id` ASC tie-break (RF-013.1).
     * Every other field of each row is preserved untouched.
     *
     * @param  array<int, array{id: string, order: int}>  $modules
     * @return array<int, array{id: string, order: int}>
     */
    public function orderModules(array $modules): array
    {
        usort($modules, fn (array $a, array $b): int => $a['order'] <=> $b['order']
            ?: strcmp((string) $a['id'], (string) $b['id']));

        return $modules;
    }

    /**
     * Stable fingerprint of the path's current persisted definition — its
     * identity plus the ordered module sequence — used to group comparable
     * collective rates (RF-013.6/.8): only percentages of the same path and
     * version are averaged together. It is not a historical snapshot: it
     * tracks the current definition and keeps no history, so structural
     * changes simply yield a different key. Non-structural edits (titles)
     * keep it.
     *
     * @param  array<int, array{id: string, order: int}>  $modules  any order; normalized internally
     */
    public function pathVersionKey(string $pathId, array $modules): string
    {
        $orderedIds = array_column($this->orderModules($modules), 'id');

        return substr(hash('sha256', json_encode([
            'path' => $pathId,
            'modules' => $orderedIds,
        ])), 0, 32);
    }

    /**
     * Place challenges under modules via the explicit `path_module_id` link
     * only (RF-013.3/.4). Challenges unlinked, missing the link key, or
     * pointing to a module outside the given set are placed nowhere. Returns
     * one entry per module id, preserving the input order of challenges.
     *
     * @param  array<int, string>  $moduleIds
     * @param  array<int, array{id: string, title: string, status: string, path_module_id?: ?string}>  $challenges
     * @return array<string, array<int, MapElement>>
     */
    public function placeChallenges(array $moduleIds, array $challenges): array
    {
        $placed = array_fill_keys($moduleIds, []);

        foreach ($challenges as $challenge) {
            $moduleId = $challenge['path_module_id'] ?? null;

            if ($moduleId === null || ! array_key_exists($moduleId, $placed)) {
                continue;
            }

            $placed[$moduleId][] = new MapElement(
                type: MapElementType::Challenge,
                id: $challenge['id'],
                title: $challenge['title'],
                sourceStatus: $challenge['status'],
            );
        }

        return $placed;
    }
}
