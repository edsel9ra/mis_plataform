<?php

namespace App\Services\Scheduling;

use App\Enums\AvailabilityMode;
use App\Models\MentorAvailabilityException;
use App\Models\Session;
use App\Services\Scheduling\Rules\AvailabilityRules;
use App\Support\DbTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Sessions a blocking availability exception would newly cover (RF-004.5:
 * the API only reports them — reprogramming or canceling stays manual).
 */
class ExceptionConflictsService
{
    /**
     * @return list<array{id: string, title: string, scheduled_at: string, duration_minutes: int}>
     */
    public function forException(MentorAvailabilityException $exception, CarbonInterface $now): array
    {
        if ($exception->mode !== AvailabilityMode::Block) {
            return [];
        }

        $startDate = $exception->start_date->toDateString();
        $endDate = $exception->end_date?->toDateString() ?? $startDate;
        $lookbackMinutes = (int) config('scheduling.max_duration_minutes', 480);

        // Coarse SQL prefilter in the exception timezone: sessions whose stored
        // wall-time start could still intersect the covered day range.
        $from = CarbonImmutable::parse("{$startDate} 00:00:00", $exception->timezone)
            ->subMinutes($lookbackMinutes);
        $to = CarbonImmutable::parse("{$endDate} 00:00:00", $exception->timezone)
            ->addDay();

        $ruleException = $exception->toRuleArray();

        return Session::query()
            ->where('status', 'scheduled')
            ->whereHas('relationship', fn ($relationship) => $relationship->where('mentor_id', $exception->user_id))
            ->where('scheduled_at', '>=', DbTime::toRaw($from))
            ->where('scheduled_at', '<', DbTime::toRaw($to))
            ->get(['id', 'relationship_id', 'title', 'scheduled_at', 'duration_minutes'])
            ->map(function (Session $session) use ($ruleException, $exception, $now): ?array {
                $start = DbTime::fromRaw($session->getRawOriginal('scheduled_at'));

                if ($start <= $now) {
                    return null;
                }

                $decision = AvailabilityRules::check(
                    [],
                    [$ruleException],
                    ['start' => $start, 'end' => $start->addMinutes((int) $session->duration_minutes)],
                    $now,
                    $exception->timezone,
                );

                if (($decision['reason'] ?? null) !== 'blocked') {
                    return null;
                }

                return [
                    'id' => $session->id,
                    'title' => $session->title,
                    'scheduled_at' => $session->scheduled_at->toJSON(),
                    'duration_minutes' => (int) $session->duration_minutes,
                    'start' => $start,
                ];
            })
            ->filter()
            ->sortBy('start')
            ->map(fn (array $entry): array => array_intersect_key($entry, array_flip(['id', 'title', 'scheduled_at', 'duration_minutes'])))
            ->values()
            ->all();
    }
}
