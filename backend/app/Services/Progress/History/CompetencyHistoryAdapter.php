<?php

namespace App\Services\Progress\History;

use App\Actions\RemoveCompetencyAssessmentLinkAction;
use App\Models\SkillTag;
use App\Models\User;
use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use Illuminate\Support\Facades\DB;

/**
 * T015.4 — competency history feed (RF-015.4, plan §1/§3). Projects the
 * audited `competency.evidence_link_changed` and
 * `competency.evidence_source_state_changed` events filtered by the
 * subject id inside properties.
 *
 * Retirements marked `reason=source_deleted` (Spec 014, CA-19.7) are
 * excluded in SQL before the LIMIT: the operational audit keeps the real
 * deleter, but the timeline must never reveal who deleted a source — the
 * deletion itself is surfaced only through the minimal deletion event.
 * Whitelisted fields only: source_id, actor_id, completed_at and notes
 * never leave the log.
 */
final class CompetencyHistoryAdapter implements HistoryAdapter
{
    private const EVENTS = [
        'competency.evidence_link_changed',
        'competency.evidence_source_state_changed',
    ];

    private const RELATION_KEYS = ['source_type', 'competency_type', 'competency_key'];

    private const ID_SQL = "('competency_activity:' || lpad(activity_log.id::text, 20, '0'))";

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
            ->where('log_name', 'progress')
            ->whereIn('event', self::EVENTS)
            ->whereRaw("(activity_log.properties::jsonb ->> 'subject_id') = ?", [$subject->id])
            ->whereRaw(
                "(activity_log.properties::jsonb ->> 'reason') IS DISTINCT FROM ?",
                [RemoveCompetencyAssessmentLinkAction::REASON_SOURCE_DELETED],
            );
    }

    private function toEvents($rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $causers = User::withTrashed()
            ->whereIn('id', $rows->pluck('causer_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $skillTags = SkillTag::query()
            ->whereIn('id', $this->skillTagKeys($rows))
            ->pluck('name', 'id');

        return $rows
            ->map(fn (object $row): array => $this->toEvent($row, $causers, $skillTags))
            ->values()
            ->all();
    }

    /**
     * Every skill_tag competency_key appearing in before/after of the page's
     * link events — one lookup for the whole page.
     */
    private function skillTagKeys($rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            if ($row->event !== 'competency.evidence_link_changed') {
                continue;
            }
            $properties = json_decode((string) $row->properties, true) ?: [];
            foreach (['before', 'after'] as $side) {
                $ref = $properties[$side] ?? null;
                if (is_array($ref) && ($ref['competency_type'] ?? null) === 'skill_tag' && isset($ref['competency_key'])) {
                    $keys[] = $ref['competency_key'];
                }
            }
        }

        return array_values(array_unique($keys));
    }

    private function toEvent(object $row, $causers, $skillTags): array
    {
        $properties = json_decode((string) $row->properties, true) ?: [];

        if ($row->event === 'competency.evidence_link_changed') {
            [$before, $after, $subjectRef, $incomplete] = $this->linkSides($properties, $row, $skillTags);
        } else {
            [$before, $missingBefore] = ProgressHistoryCalculator::whitelist($properties['before'] ?? [], ['state']);
            [$after, $missingAfter] = ProgressHistoryCalculator::whitelist($properties['after'] ?? [], ['state']);
            $subjectRef = [
                'source_type' => $properties['source_type'] ?? null,
                'source_id' => $properties['source_id'] ?? null,
                'link_ids' => array_values($properties['link_ids'] ?? []),
            ];
            $incomplete = [...$missingBefore, ...$missingAfter];
        }

        $causer = $row->causer_id !== null ? $causers->get($row->causer_id) : null;
        $actor = $causer !== null ? ['id' => $causer->id, 'name' => $causer->name] : null;

        return ProgressHistoryCalculator::event(
            id: 'competency_activity:' . str_pad((string) $row->id, 20, '0', STR_PAD_LEFT),
            type: $row->event,
            domain: 'competencies',
            occurredAt: ProgressHistoryCalculator::occurredAt($properties['occurred_at'] ?? null, $row->created_at),
            actor: $actor,
            subjectRef: $subjectRef,
            before: $before,
            after: $after,
            incomplete: $incomplete,
        );
    }

    /**
     * before/after for a link event: an absent key yields null plus an
     * incomplete marker; a present null is the legitimate boundary of a
     * creation or removal; an array is whitelisted and extended with the
     * resolved competency name (never itself incomplete). source_id is
     * never emitted.
     *
     * @return array{0: ?array, 1: ?array, 2: array, 3: list<string>}
     */
    private function linkSides(array $properties, object $row, $skillTags): array
    {
        $incomplete = [];
        $sides = [];

        foreach (['before', 'after'] as $side) {
            if (! array_key_exists($side, $properties)) {
                $sides[$side] = null;
                $incomplete[] = $side;

                continue;
            }

            $ref = $properties[$side];
            if ($ref === null) {
                $sides[$side] = null;

                continue;
            }

            [$filtered, $missing] = ProgressHistoryCalculator::whitelist(
                is_array($ref) ? $ref : [],
                self::RELATION_KEYS,
            );
            $filtered['competency_name'] = ($filtered['competency_type'] ?? null) === 'skill_tag'
                ? $skillTags->get($filtered['competency_key'] ?? null)
                : null;

            $sides[$side] = $filtered;
            $incomplete = [...$incomplete, ...$missing];
        }

        $subjectRef = ['link_id' => $properties['link_id'] ?? $row->subject_id];

        return [$sides['before'], $sides['after'], $subjectRef, $incomplete];
    }
}
