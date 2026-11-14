<?php

namespace App\Http\Resources;

use App\Services\Progress\Map\AggregateMetric;
use App\Services\Progress\Map\CollectiveLearningAggregate;
use App\Services\Progress\Map\PathAggregateRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Collective learning aggregate (RF-013.5-.8). Suppression never serializes
 * counts, numerators, denominators, base sizes or member identities — only
 * `state` + a translatable `suppression_reason` code.
 */
class CollectiveLearningAggregateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CollectiveLearningAggregate $aggregate */
        $aggregate = $this->resource;

        if ($aggregate->state === CollectiveLearningAggregate::STATE_SUPPRESSED) {
            return [
                'state' => 'suppressed',
                'suppression_reason' => $aggregate->suppressionReason?->value,
            ];
        }

        return [
            'state' => 'available',
            'suppression_reason' => null,
            'activity_rate' => $aggregate->activityRate === null
                ? null
                : $this->metric($aggregate->activityRate),
            'rates' => array_map(
                fn (PathAggregateRate $rate): array => [
                    'path_id' => $rate->pathId,
                    'version_key' => $rate->versionKey,
                    'title' => $rate->title,
                    'rate' => $this->metric($rate->metric),
                ],
                $aggregate->rates,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metric(AggregateMetric $metric): array
    {
        if ($metric->state === AggregateMetric::STATE_SUPPRESSED) {
            return [
                'state' => 'suppressed',
                'suppression_reason' => $metric->suppressionReason?->value,
            ];
        }

        return [
            'state' => 'value',
            'value' => $metric->value,
            'suppression_reason' => null,
        ];
    }
}
