<?php

namespace App\Services\Progress\History;

use App\Models\PathModule;
use App\Models\User;
use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use Illuminate\Support\Facades\DB;

/**
 * T015.3 — module completion feed (RF-015.3/.7/.8, plan §1). Reads the
 * audited `module_completion.granted` / `.revoked` events filtered by the
 * subject id inside properties. The `user_module_completions` table is never
 * consulted: a current-state row is not an event (RF-015.8).
 *
 * Actor is `system` by design — the human/action trigger lives inside
 * `triggered_by` (RF-17, plan §1).
 */
final class ModuleCompletionHistoryAdapter implements HistoryAdapter
{
    private const EVENTS = ['module_completion.granted', 'module_completion.revoked'];

    public function dated(User $subject, ?array $after, int $limit): array
    {
        $query = DB::table('activity_log')
            ->select(['id', 'event', 'properties', 'created_at'])
            ->where('log_name', 'module_completion')
            ->whereIn('event', self::EVENTS)
            ->whereRaw("(activity_log.properties::jsonb ->> 'user_id') = ?", [$subject->id]);

        $rows = HistoryCursorQuery::apply(
            $query,
            "COALESCE((activity_log.properties::jsonb ->> 'occurred_at')::timestamptz, activity_log.created_at AT TIME ZONE ?)",
            [config('app.timezone')],
            "('module_completion:' || lpad(activity_log.id::text, 20, '0'))",
            $after,
            $limit,
        )->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $moduleIds = $rows
            ->map(fn (object $row) => (json_decode((string) $row->properties, true) ?: [])['path_module_id'] ?? null)
            ->filter()
            ->unique();

        $modules = PathModule::query()
            ->whereIn('id', $moduleIds)
            ->pluck('title', 'id');

        return $rows
            ->map(fn (object $row): array => $this->toEvent($row, $modules))
            ->values()
            ->all();
    }

    public function undated(User $subject, int $cap): array
    {
        return [];
    }

    private function toEvent(object $row, $modules): array
    {
        $properties = json_decode((string) $row->properties, true) ?: [];
        $triggeredBy = is_array($properties['triggered_by'] ?? null) ? $properties['triggered_by'] : [];

        $incomplete = [];
        if (($triggeredBy['event'] ?? null) === null) {
            $incomplete[] = 'triggered_by';
        }

        $actor = ($properties['actor'] ?? null) === 'system' ? ['kind' => 'system'] : null;

        return ProgressHistoryCalculator::event(
            id: 'module_completion:' . str_pad((string) $row->id, 20, '0', STR_PAD_LEFT),
            type: $row->event,
            domain: 'activities',
            occurredAt: ProgressHistoryCalculator::occurredAt($properties['occurred_at'] ?? null, $row->created_at),
            actor: $actor,
            subjectRef: [
                'path_module_id' => $properties['path_module_id'] ?? null,
                'title' => $modules->get($properties['path_module_id'] ?? null),
            ],
            before: null,
            after: [
                'result' => $properties['result'] ?? null,
                'triggered_by' => [
                    'event' => $triggeredBy['event'] ?? null,
                    'actor_id' => $triggeredBy['actor_id'] ?? null,
                    'challenge_id' => $triggeredBy['challenge_id'] ?? null,
                ],
            ],
            incomplete: $incomplete,
        );
    }
}
