<?php

namespace App\Actions;

use App\Models\AssessmentResult;
use App\Models\CompetencyAssessmentLink;
use App\Models\PersonalityAssessment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Retire every evidence link pointing at a source before that source row is
 * deleted (RF-014.4, .6).
 *
 * The restrictive FK intentionally blocks deleting a linked source. Each
 * link is retired through the audited removal path — actor, timestamp and
 * an explicitly absent resulting relation are recorded per link, so no
 * history is silently lost. Before each removal the minimal deletion event
 * of the retention policy is recorded (docs/politica-retencion.md §4–§5,
 * plan §9): both registers coexist — the operational audit event
 * (competency.evidence_link_changed) is untouched, only marked with
 * REASON_SOURCE_DELETED so Spec 015 history can exclude it (plan §3).
 */
class RetireEvidenceLinksForSourceAction
{
    public function __construct(
        private RemoveCompetencyAssessmentLinkAction $remove,
        private RecordEvidenceDeletionEventAction $record,
    ) {}

    public function execute(Model $source, User $actor): int
    {
        $column = match (true) {
            $source instanceof AssessmentResult => 'assessment_result_id',
            $source instanceof PersonalityAssessment => 'personality_assessment_id',
            default => throw new InvalidArgumentException('Unsupported evidence source: '.$source::class),
        };

        $links = CompetencyAssessmentLink::query()
            ->where($column, $source->getKey())
            ->with('user')
            ->get();

        // One instant per retirement batch; the transaction keeps the
        // deletion events and link removals atomic even when a caller
        // forgets to wrap the source delete itself.
        $now = CarbonImmutable::now();

        DB::transaction(function () use ($links, $actor, $now): void {
            foreach ($links as $link) {
                $this->record->execute($link, $actor, $now);
                $this->remove->execute(
                    $link->user,
                    $actor,
                    $link,
                    $now,
                    RemoveCompetencyAssessmentLinkAction::REASON_SOURCE_DELETED,
                );
            }
        });

        return $links->count();
    }
}
