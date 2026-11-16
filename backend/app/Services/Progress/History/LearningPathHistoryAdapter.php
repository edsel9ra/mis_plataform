<?php

namespace App\Services\Progress\History;

use App\Models\LearningPath;
use App\Models\User;
use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use Illuminate\Support\Facades\DB;

/**
 * T015.3 — learning path feed (RF-015.3/.7/.8, plan §1). One event per
 * recorded date on `user_learning_progress`: `started_at` and
 * `completed_at` when present — a UNION ALL keyed by (at, event_id).
 *
 * A row without any date produces nothing (no fact to show, RF-015.8).
 * The actor is not recorded on the source, so it is reported as incomplete
 * rather than inferred. `status`, `progress` and `current_module_id` never
 * leave the table.
 */
final class LearningPathHistoryAdapter implements HistoryAdapter
{
    public function dated(User $subject, ?array $after, int $limit): array
    {
        $started = DB::table('user_learning_progress')
            ->select('id', 'learning_path_id', 'started_at as at')
            ->selectRaw("('learning_progress:' || id::text || ':started') as event_id")
            ->selectRaw("'started' as kind")
            ->where('user_id', $subject->id)
            ->whereNotNull('started_at');

        $completed = DB::table('user_learning_progress')
            ->select('id', 'learning_path_id', 'completed_at as at')
            ->selectRaw("('learning_progress:' || id::text || ':completed') as event_id")
            ->selectRaw("'completed' as kind")
            ->where('user_id', $subject->id)
            ->whereNotNull('completed_at');

        $query = DB::query()->fromSub($started->unionAll($completed), 'e')
            ->select('e.*');

        $rows = HistoryCursorQuery::apply(
            $query,
            '(e.at AT TIME ZONE ?)',
            [config('app.timezone')],
            'e.event_id',
            $after,
            $limit,
        )->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $paths = LearningPath::withTrashed()
            ->whereIn('id', $rows->pluck('learning_path_id')->unique())
            ->pluck('title', 'id');

        return $rows
            ->map(fn (object $row): array => $this->toEvent($row, $paths))
            ->values()
            ->all();
    }

    public function undated(User $subject, int $cap): array
    {
        return [];
    }

    private function toEvent(object $row, $paths): array
    {
        return ProgressHistoryCalculator::event(
            id: $row->event_id,
            type: "learning_path.{$row->kind}",
            domain: 'activities',
            occurredAt: ProgressHistoryCalculator::occurredAt($row->at),
            actor: null,
            subjectRef: [
                'learning_path_id' => $row->learning_path_id,
                'title' => $paths->get($row->learning_path_id),
            ],
            before: null,
            after: null,
        );
    }
}
