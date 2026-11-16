<?php

namespace App\Services\Progress\History;

use App\Enums\ObjectiveScope;
use App\Models\Objective;
use App\Models\User;
use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use Illuminate\Support\Facades\DB;

/**
 * T015.2 — objectives feed (RF-015.2/.7/.8, plan §1). Reads
 * `activity_log` rows with log_name 'objective' for INDIVIDUAL objectives
 * owned by the subject (plan D-2), including soft-deleted ones — their
 * verified history does not disappear.
 *
 * Only `created` and `updated` rows whose `attributes.status` is present
 * are events: an `updated` without a status change produces no entry.
 * Whitelisted fields only — success_criteria, due_at, owner_id and
 * description never leave the log.
 */
final class ObjectiveHistoryAdapter implements HistoryAdapter
{
    public function dated(User $subject, ?array $after, int $limit): array
    {
        $query = DB::table('activity_log')
            ->select(['id', 'event', 'properties', 'subject_id', 'causer_id', 'created_at'])
            ->where('log_name', 'objective')
            ->where('subject_type', (new Objective)->getMorphClass())
            ->whereIn('subject_id', $this->individualObjectiveIds($subject))
            ->whereRaw(
                "(event = 'created' OR (event = 'updated' AND (properties::jsonb -> 'attributes' -> 'status') IS NOT NULL))",
            );

        $rows = HistoryCursorQuery::apply(
            $query,
            '(activity_log.created_at AT TIME ZONE ?)',
            [config('app.timezone')],
            "('objective_activity:' || lpad(activity_log.id::text, 20, '0'))",
            $after,
            $limit,
        )->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $objectives = Objective::withTrashed()
            ->whereIn('id', $rows->pluck('subject_id')->unique())
            ->pluck('title', 'id');

        $causers = User::withTrashed()
            ->whereIn('id', $rows->pluck('causer_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return $rows
            ->map(fn (object $row): array => $this->toEvent($row, $objectives, $causers))
            ->values()
            ->all();
    }

    public function undated(User $subject, int $cap): array
    {
        return [];
    }

    private function individualObjectiveIds(User $subject)
    {
        return Objective::withTrashed()
            ->where('scope', ObjectiveScope::Individual)
            ->where('owner_type', 'user')
            ->where('owner_id', $subject->id)
            ->select('id');
    }

    private function toEvent(object $row, $objectives, $causers): array
    {
        $properties = json_decode((string) $row->properties, true) ?: [];
        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];

        $before = null;
        $incomplete = [];

        if ($row->event === 'created') {
            $type = 'objective.created';
            [$after, $missing] = ProgressHistoryCalculator::whitelist($attributes, ['status']);
            $incomplete = $missing;
        } else {
            $type = 'objective.status_changed';
            [$before, $missingBefore] = ProgressHistoryCalculator::whitelist($old, ['status']);
            [$after, $missingAfter] = ProgressHistoryCalculator::whitelist($attributes, ['status']);
            $incomplete = [...$missingBefore, ...$missingAfter];
        }

        $causer = $row->causer_id !== null ? $causers->get($row->causer_id) : null;

        return ProgressHistoryCalculator::event(
            id: 'objective_activity:' . str_pad((string) $row->id, 20, '0', STR_PAD_LEFT),
            type: $type,
            domain: 'objectives',
            occurredAt: ProgressHistoryCalculator::occurredAt($row->created_at),
            actor: $causer !== null ? ['id' => $causer->id, 'name' => $causer->name] : null,
            subjectRef: [
                'objective_id' => $row->subject_id,
                'title' => $objectives->get($row->subject_id),
            ],
            before: $before,
            after: $after,
            incomplete: $incomplete,
        );
    }
}
