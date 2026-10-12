<?php

namespace App\Actions;

use App\Models\MentorshipRelationship;
use App\Models\SystemicMap;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertSystemicMapAction
{
    /** @param array<string, mixed> $data */
    public function execute(
        User $mentor,
        User $mentee,
        MentorshipRelationship $relationship,
        array $data,
    ): SystemicMap {
        return DB::transaction(function () use ($mentor, $mentee, $relationship, $data) {
            $map = SystemicMap::firstOrCreate(
                ['relationship_id' => $relationship->id],
                [
                    'mentor_id' => $mentor->id,
                    'mentee_id' => $mentee->id,
                    'title' => 'Mapa sistemico',
                    'is_private' => true,
                ],
            );

            $map->update([
                'mentor_id' => $mentor->id,
                'mentee_id' => $mentee->id,
                'title' => $data['title'] ?? $map->title,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $map->notes,
                'is_private' => true,
            ]);

            if (array_key_exists('nodes', $data)) {
                $map->edges()->delete();
                $map->nodes()->delete();

                $nodesByKey = collect($data['nodes'] ?? [])->mapWithKeys(function (array $node, int $index) use ($map) {
                    $key = (string) ($node['key'] ?? $node['id'] ?? "node-{$index}");
                    $position = $node['position'] ?? array_filter([
                        'x' => $node['x'] ?? null,
                        'y' => $node['y'] ?? null,
                        'color' => $node['color'] ?? null,
                    ], fn ($value) => $value !== null);
                    $created = $map->nodes()->create([
                        'node_key' => $key,
                        'label' => $node['label'],
                        'kind' => $node['kind'] ?? null,
                        'position' => $position ?: null,
                        'notes' => $node['notes'] ?? null,
                    ]);

                    return [$key => $created, (string) ($node['id'] ?? $key) => $created];
                });
            } else {
                $nodesByKey = $map->nodes()->get()->keyBy('node_key');
            }

            if (array_key_exists('edges', $data)) {
                foreach ($data['edges'] ?? [] as $index => $edge) {
                    $from = $nodesByKey->get($edge['from'] ?? $edge['source'] ?? '');
                    $to = $nodesByKey->get($edge['to'] ?? $edge['target'] ?? '');

                    if (!$from && !empty($edge['from_node_id'])) {
                        $from = $map->nodes()->whereKey($edge['from_node_id'])->first();
                    }
                    if (!$to && !empty($edge['to_node_id'])) {
                        $to = $map->nodes()->whereKey($edge['to_node_id'])->first();
                    }

                    if (!$from || !$to) {
                        throw ValidationException::withMessages([
                            "edges.{$index}" => ['Both edge endpoints must belong to this map.'],
                        ]);
                    }

                    $map->edges()->create([
                        'from_node_id' => $from->id,
                        'to_node_id' => $to->id,
                        'label' => $edge['label'] ?? null,
                        'metadata' => $edge['metadata'] ?? null,
                    ]);
                }
            }

            return $map->load(['nodes', 'edges.fromNode', 'edges.toNode']);
        });
    }
}
