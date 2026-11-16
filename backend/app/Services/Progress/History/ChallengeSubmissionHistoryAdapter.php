<?php

namespace App\Services\Progress\History;

use App\Models\Challenge;
use App\Models\User;
use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use Illuminate\Support\Facades\DB;

/**
 * T015.3 — challenge submission feed (RF-015.3/.7/.8, plan §1). One event
 * per recorded fact: `submitted_at` (NOT NULL in the schema) and
 * `reviewed_at` when present — a UNION ALL keyed by (at, event_id).
 *
 * The actor is never emitted: the row does not record who submitted or
 * reviewed, and the uploader may differ from the mentee — no inference
 * (RF-015.8). `note` and the current `status` (a snapshot, not the status at
 * that moment) never leave the table.
 */
final class ChallengeSubmissionHistoryAdapter implements HistoryAdapter
{
    public function dated(User $subject, ?array $after, int $limit): array
    {
        $submitted = DB::table('challenge_submissions')
            ->select('id', 'challenge_id', 'submitted_at as at')
            ->selectRaw("('challenge_submission:' || id::text || ':submitted') as event_id")
            ->selectRaw("'submitted' as kind")
            ->where('mentee_id', $subject->id)
            ->whereIn('challenge_id', $this->subjectChallengeIds($subject));

        $reviewed = DB::table('challenge_submissions')
            ->select('id', 'challenge_id', 'reviewed_at as at')
            ->selectRaw("('challenge_submission:' || id::text || ':reviewed') as event_id")
            ->selectRaw("'reviewed' as kind")
            ->where('mentee_id', $subject->id)
            ->whereIn('challenge_id', $this->subjectChallengeIds($subject))
            ->whereNotNull('reviewed_at');

        $query = DB::query()->fromSub($submitted->unionAll($reviewed), 'e')
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

        $challenges = Challenge::withTrashed()
            ->whereIn('id', $rows->pluck('challenge_id')->unique())
            ->pluck('title', 'id');

        return $rows
            ->map(fn (object $row): array => $this->toEvent($row, $challenges))
            ->values()
            ->all();
    }

    public function undated(User $subject, int $cap): array
    {
        return [];
    }

    private function subjectChallengeIds(User $subject)
    {
        return Challenge::withTrashed()
            ->where('mentee_id', $subject->id)
            ->select('id');
    }

    private function toEvent(object $row, $challenges): array
    {
        return ProgressHistoryCalculator::event(
            id: $row->event_id,
            type: "challenge_submission.{$row->kind}",
            domain: 'activities',
            occurredAt: ProgressHistoryCalculator::occurredAt($row->at),
            actor: null,
            subjectRef: [
                'challenge_id' => $row->challenge_id,
                'title' => $challenges->get($row->challenge_id),
                'submission_id' => $row->id,
            ],
            before: null,
            after: null,
        );
    }
}
