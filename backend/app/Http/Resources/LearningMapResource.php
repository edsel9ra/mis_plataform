<?php

namespace App\Http\Resources;

use App\Services\Progress\Map\MapModule;
use App\Services\Progress\Map\MapPath;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Individual learning map (RF-013.1-.4). Shared metric parts keep the exact
 * keys of the Spec 012 learning-paths contract; the map adds `version_key`
 * per path and `elements` per module. No inferred relations, ever.
 */
class LearningMapResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MapPath $path */
        $path = $this->resource;

        return [
            'learning_path' => [
                'id' => $path->id,
                'title' => $path->title,
            ],
            'version_key' => $path->versionKey,
            'rate' => $path->rate->toArray(),
            'modules' => array_map(
                fn (MapModule $module): array => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'position' => $module->order,
                    'activity_rate' => $module->activityRate->toArray(),
                    'completion' => [
                        'state' => $module->completion->state,
                        'completed_at' => $module->completion->completedAt,
                    ],
                    'elements' => array_map(
                        fn ($element): array => [
                            'type' => $element->type->value,
                            'id' => $element->id,
                            'title' => $element->title,
                            'source_status' => $element->sourceStatus,
                        ],
                        $module->elements,
                    ),
                ],
                $path->modules,
            ),
        ];
    }
}
