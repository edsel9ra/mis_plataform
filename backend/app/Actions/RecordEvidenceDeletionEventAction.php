<?php

namespace App\Actions;

use App\Models\CompetencyAssessmentLink;
use App\Models\EvidenceDeletionActorLink;
use App\Models\EvidenceDeletionEvent;
use App\Models\User;
use App\Services\Progress\Calculators\EvidenceDeletionEventCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Record the minimal deletion event for one retired evidence link
 * (docs/politica-retencion.md §4–§5, plan §9): discriminators, a random
 * per-event actor pseudonym and dates — never the source id, link id or
 * actor id. The real actor is stored only in the separate correspondence
 * table. Returns null when retention is disabled (§5.3).
 */
class RecordEvidenceDeletionEventAction
{
    public function execute(
        CompetencyAssessmentLink $link,
        User $actor,
        CarbonImmutable $now,
    ): ?EvidenceDeletionEvent {
        $months = EvidenceDeletionEventCalculator::retentionMonths(
            config('progress.deletion_event_retention_months'),
        );

        if ($months === null) {
            return null;
        }

        $payload = EvidenceDeletionEventCalculator::minimalEvent(
            [
                'source_type' => $link->sourceType(),
                'competency_type' => $link->competencyType(),
                'competency_key' => (string) $link->competencyKey(),
            ],
            $link->user_id,
            $now,
            $months,
            (string) Str::uuid(),
        );

        return DB::transaction(function () use ($payload, $actor): EvidenceDeletionEvent {
            $event = EvidenceDeletionEvent::create($payload);

            EvidenceDeletionActorLink::create([
                'event_id' => $event->id,
                'actor_id' => $actor->id,
            ]);

            return $event;
        });
    }
}
