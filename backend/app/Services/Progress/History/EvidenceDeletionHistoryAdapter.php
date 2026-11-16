<?php

namespace App\Services\Progress\History;

use App\Models\SkillTag;
use App\Models\User;
use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * T015.5 — minimal deletion events in the timeline (RF-015.9, CA-19.7,
 * docs/politica-retencion.md §4–§5). Reads `evidence_deletion_events`, the
 * only register that may surface a source deletion: the real actor lives
 * solely in `evidence_deletion_actor_links` and is never queried — the
 * actor is reported as `['kind' => 'hidden']` by design.
 *
 * Time semantics: `occurred_at` is timestamp without time zone holding
 * the app-timezone wall clock, like every other source (T014.14 converted
 * the former timestamptz columns); the SQL instant is the usual
 * `(col AT TIME ZONE ?)` and PHP parses the same wall clock.
 *
 * Expiry filter mirrors `IdentifyEvidenceDeletionActorAction` and
 * `ExpireEvidenceDeletionEvents` (local wall clocks on both sides), so an
 * expired-but-not-yet-purged event is hidden consistently. Undated events
 * are impossible (`occurred_at NOT NULL`).
 */
final class EvidenceDeletionHistoryAdapter implements HistoryAdapter
{
    public function dated(User $subject, ?array $after, int $limit): array
    {
        $query = DB::table('evidence_deletion_events')
            ->select(['id', 'source_type', 'competency_type', 'competency_key', 'occurred_at'])
            ->where('subject_id', $subject->id)
            ->where('expires_at', '>', CarbonImmutable::now());

        $rows = HistoryCursorQuery::apply(
            $query,
            '(evidence_deletion_events.occurred_at AT TIME ZONE ?)',
            [config('app.timezone')],
            "('evidence_deletion:' || evidence_deletion_events.id::text)",
            $after,
            $limit,
        )->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $skillTags = SkillTag::query()
            ->whereIn(
                'id',
                $rows->where('competency_type', 'skill_tag')->pluck('competency_key')->unique(),
            )
            ->pluck('name', 'id');

        return $rows
            ->map(fn (object $row): array => $this->toEvent($row, $skillTags))
            ->values()
            ->all();
    }

    public function undated(User $subject, int $cap): array
    {
        return [];
    }

    private function toEvent(object $row, $skillTags): array
    {
        return ProgressHistoryCalculator::event(
            id: 'evidence_deletion:' . $row->id,
            type: 'competency.evidence_source_deleted',
            domain: 'competencies',
            occurredAt: ProgressHistoryCalculator::occurredAt($row->occurred_at),
            actor: ['kind' => 'hidden'],
            subjectRef: ['event_id' => $row->id],
            before: [
                'source_type' => $row->source_type,
                'competency_type' => $row->competency_type,
                'competency_key' => $row->competency_key,
                'competency_name' => $row->competency_type === 'skill_tag'
                    ? $skillTags->get($row->competency_key)
                    : null,
            ],
            after: null,
        );
    }
}
