<?php

namespace App\Observers;

use App\Models\PersonalityAssessment;
use Illuminate\Support\Facades\Auth;

/**
 * Whitelisted event for Spec 015 (RF-014.4, .6–.7): only a genuinely
 * persisted completed_at transition on a PersonalityAssessment that still
 * has active links produces an immutable event. No synthetic transitions —
 * `created` cannot have pre-existing links (FK) and `deleted` is blocked
 * by the restrictive FK while linked (links are retired first through the
 * audited path). AssessmentResult.completed_at is NOT NULL and has no
 * incomplete transition, so no observer is registered for it.
 *
 * The link itself, its history events and UserSkill.level are never
 * touched here.
 */
class EvidenceSourceStateObserver
{
    public function updated(PersonalityAssessment $assessment): void
    {
        if (! $assessment->wasChanged('completed_at')) {
            return;
        }

        $linkIds = $assessment->competencyAssessmentLinks()->pluck('id')->all();

        if ($linkIds === []) {
            return;
        }

        $before = $assessment->getOriginal('completed_at');
        $after = $assessment->completed_at;
        $actor = Auth::user();

        $activity = activity('progress')
            ->performedOn($assessment)
            ->event('competency.evidence_source_state_changed')
            ->withProperties([
                'subject_id' => $assessment->user_id,
                'source_type' => 'personality_assessment',
                'source_id' => $assessment->id,
                'link_ids' => $linkIds,
                'actor_id' => $actor?->id,
                'before' => [
                    'completed_at' => $before?->toIso8601String(),
                    'state' => $before === null ? 'incomplete' : 'current',
                ],
                'after' => [
                    'completed_at' => $after?->toIso8601String(),
                    'state' => $after === null ? 'incomplete' : 'current',
                ],
                'occurred_at' => now()->toIso8601String(),
            ]);

        if ($actor !== null) {
            $activity->causedBy($actor);
        }

        $activity->log('competency.evidence_source_state_changed');
    }
}
