<?php

namespace App\Services\Progress\History;

use App\Models\Challenge;
use App\Models\User;
use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use Illuminate\Support\Facades\DB;

/**
 * T015.3 — challenge activity feed (RF-015.3/.7/.8, plan §1). Reads
 * `activity_log` rows with log_name 'challenge' for challenges owned by the
 * subject (mentee_id, including soft-deleted ones) and emits the audited
 * `challenge.status_transition` and `challenge.module_link_changed` events.
 *
 * Whitelisted fields only: status before/after, path_module_id before/after
 * and the teardown reason. Descriptions, instructions, progress and the raw
 * actor id in properties never leave the log.
 */
final class ChallengeActivityHistoryAdapter implements HistoryAdapter
{
    private const EVENTS = ['challenge.status_transition', 'challenge.module_link_changed'];

    private const ID_SQL = "('challenge_activity:' || lpad(activity_log.id::text, 20, '0'))";

    public function dated(User $subject, ?array $after, int $limit): array
    {
        $rows = HistoryCursorQuery::apply(
            $this->baseQuery($subject)->whereRaw(
                "((activity_log.properties::jsonb ->> 'occurred_at') IS NOT NULL OR activity_log.created_at IS NOT NULL)",
            ),
            "COALESCE((activity_log.properties::jsonb ->> 'occurred_at')::timestamptz, activity_log.created_at AT TIME ZONE ?)",
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
            ->whereRaw(
                "((activity_log.properties::jsonb ->> 'occurred_at') IS NULL AND activity_log.created_at IS NULL)",
            )
            ->orderByRaw('('.self::ID_SQL.') COLLATE "C"')
            ->limit($cap)
            ->get();

        return $this->toEvents($rows);
    }

    private function baseQuery(User $subject)
    {
        return DB::table('activity_log')
            ->select(['id', 'event', 'properties', 'subject_id', 'causer_id', 'created_at'])
            ->where('log_name', 'challenge')
            ->where('subject_type', (new Challenge)->getMorphClass())
            ->whereIn('subject_id', $this->subjectChallengeIds($subject))
            ->whereIn('event', self::EVENTS);
    }

    private function toEvents($rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $challenges = Challenge::withTrashed()
            ->whereIn('id', $rows->pluck('subject_id')->unique())
            ->pluck('title', 'id');

        $causers = User::withTrashed()
            ->whereIn('id', $rows->pluck('causer_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return $rows
            ->map(fn (object $row): array => $this->toEvent($row, $challenges, $causers))
            ->values()
            ->all();
    }

    private function subjectChallengeIds(User $subject)
    {
        return Challenge::withTrashed()
            ->where('mentee_id', $subject->id)
            ->select('id');
    }

    private function toEvent(object $row, $challenges, $causers): array
    {
        $properties = json_decode((string) $row->properties, true) ?: [];
        $incomplete = [];

        if ($row->event === 'challenge.module_link_changed') {
            [$before, $missingBefore] = $this->moduleRef($properties['before'] ?? null);
            [$after, $missingAfter] = $this->moduleRef($properties['after'] ?? null);
            $incomplete = [...$missingBefore, ...$missingAfter];

            $reason = $properties['reason'] ?? null;
            if (is_string($reason) && $reason !== '') {
                $after['reason'] = $reason;
            }

            $type = 'challenge.module_link_changed';
        } else {
            [$before, $missingBefore] = ProgressHistoryCalculator::whitelist($properties['before'] ?? [], ['status']);
            [$after, $missingAfter] = ProgressHistoryCalculator::whitelist($properties['after'] ?? [], ['status']);
            $incomplete = [...$missingBefore, ...$missingAfter];

            $type = 'challenge.status_transition';
        }

        $causer = $row->causer_id !== null ? $causers->get($row->causer_id) : null;
        $actor = $causer !== null
            ? ['id' => $causer->id, 'name' => $causer->name]
            : (($properties['actor'] ?? null) === 'system' ? ['kind' => 'system'] : null);

        return ProgressHistoryCalculator::event(
            id: 'challenge_activity:' . str_pad((string) $row->id, 20, '0', STR_PAD_LEFT),
            type: $type,
            domain: 'activities',
            occurredAt: ProgressHistoryCalculator::occurredAt($properties['occurred_at'] ?? null, $row->created_at),
            actor: $actor,
            subjectRef: [
                'challenge_id' => $row->subject_id,
                'title' => $challenges->get($row->subject_id),
            ],
            before: $before,
            after: $after,
            incomplete: $incomplete,
        );
    }

    /**
     * A null path_module_id is a legitimate boundary (link created or
     * removed) — only an absent key counts as incomplete (RF-015.8).
     *
     * @return array{0: array, 1: list<string>}
     */
    private function moduleRef(mixed $ref): array
    {
        $ref = is_array($ref) ? $ref : [];

        $missing = array_key_exists('path_module_id', $ref) ? [] : ['path_module_id'];

        return [['path_module_id' => $ref['path_module_id'] ?? null], $missing];
    }
}
