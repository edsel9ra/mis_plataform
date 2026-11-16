<?php

namespace App\Actions;

use App\Models\CompetencyAssessmentLink;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Retire one competency–assessment link in a transaction (RF-014.4).
 *
 * The row is removed and the audit event records the previous relation
 * with an explicitly absent resulting one. A link owned by another subject
 * fails as ModelNotFound so its existence is not disclosed. UserSkill.level
 * and the source result are never touched.
 */
class RemoveCompetencyAssessmentLinkAction
{
    /**
     * Marker for retirements caused by a source deletion (Spec 015 plan §3):
     * the operational audit keeps the real actor while the competency
     * history adapter excludes these rows so the deleter is never revealed.
     */
    public const REASON_SOURCE_DELETED = 'source_deleted';

    public function execute(
        User $subject,
        User $actor,
        CompetencyAssessmentLink $link,
        CarbonInterface $now,
        ?string $reason = null,
    ): void {
        DB::transaction(function () use ($subject, $actor, $link, $now, $reason): void {
            $locked = CompetencyAssessmentLink::query()
                ->whereKey($link->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->user_id !== $subject->id) {
                throw (new ModelNotFoundException)->setModel(CompetencyAssessmentLink::class, [$link->getKey()]);
            }

            $before = [
                'source_type' => $locked->sourceType(),
                'source_id' => $locked->assessment_result_id ?? $locked->personality_assessment_id,
                'competency_type' => $locked->competencyType(),
                'competency_key' => $locked->competencyKey(),
            ];

            $locked->delete();

            $properties = [
                'link_id' => $locked->id,
                'subject_id' => $subject->id,
                'actor_id' => $actor->id,
                'before' => $before,
                'after' => null,
                'occurred_at' => $now->toIso8601String(),
            ];

            if ($reason !== null) {
                $properties['reason'] = $reason;
            }

            activity('progress')
                ->causedBy($actor)
                ->performedOn($locked)
                ->event('competency.evidence_link_changed')
                ->withProperties($properties)
                ->log('competency.evidence_link_changed');
        });
    }
}
