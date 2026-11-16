<?php

namespace App\Services\Progress\History;

use App\Enums\ObjectiveScope;
use App\Models\Objective;
use App\Models\User;
use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use Illuminate\Support\Facades\DB;

/**
 * T015.2 — objective evidence feed (RF-015.2/.7/.8, plan §1). One event
 * per `objective_evidences` row whose objective is an INDIVIDUAL objective
 * owned by the subject (including soft-deleted objectives).
 *
 * Only `has_attachment` is exposed: the note, the media asset id and any
 * path/url never leave the table.
 */
final class ObjectiveEvidenceHistoryAdapter implements HistoryAdapter
{
    private const ID_SQL = "('objective_evidence:' || objective_evidences.id::text)";

    public function dated(User $subject, ?array $after, int $limit): array
    {
        $rows = HistoryCursorQuery::apply(
            $this->baseQuery($subject)->whereNotNull('objective_evidences.created_at'),
            '(objective_evidences.created_at AT TIME ZONE ?)',
            [config('app.timezone')],
            self::ID_SQL,
            $after,
            $limit,
        )->get();

        return $this->toEvents($rows);
    }

    public function undated(User $subject, int $cap): array
    {
        $rows = $this->baseQuery($subject)
            ->whereNull('objective_evidences.created_at')
            ->orderByRaw('('.self::ID_SQL.') COLLATE "C"')
            ->limit($cap)
            ->get();

        return $this->toEvents($rows);
    }

    private function baseQuery(User $subject)
    {
        return DB::table('objective_evidences')
            ->select(['id', 'objective_id', 'user_id', 'media_asset_id', 'created_at'])
            ->whereIn('objective_id', $this->individualObjectiveIds($subject));
    }

    private function toEvents($rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $objectives = Objective::withTrashed()
            ->whereIn('id', $rows->pluck('objective_id')->unique())
            ->pluck('title', 'id');

        $authors = User::withTrashed()
            ->whereIn('id', $rows->pluck('user_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return $rows
            ->map(fn (object $row): array => $this->toEvent($row, $objectives, $authors))
            ->values()
            ->all();
    }

    private function individualObjectiveIds(User $subject)
    {
        return Objective::withTrashed()
            ->where('scope', ObjectiveScope::Individual)
            ->where('owner_type', 'user')
            ->where('owner_id', $subject->id)
            ->select('id');
    }

    private function toEvent(object $row, $objectives, $authors): array
    {
        $author = $row->user_id !== null ? $authors->get($row->user_id) : null;

        return ProgressHistoryCalculator::event(
            id: 'objective_evidence:' . $row->id,
            type: 'objective.evidence_added',
            domain: 'objectives',
            occurredAt: ProgressHistoryCalculator::occurredAt($row->created_at),
            actor: $author !== null ? ['id' => $author->id, 'name' => $author->name] : null,
            subjectRef: [
                'objective_id' => $row->objective_id,
                'title' => $objectives->get($row->objective_id),
                'evidence_id' => $row->id,
            ],
            before: null,
            after: ['has_attachment' => $row->media_asset_id !== null],
        );
    }
}
