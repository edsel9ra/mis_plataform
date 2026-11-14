<?php

namespace App\Services\Scheduling;

use App\Models\MentorAvailabilityException;
use App\Models\MentorAvailabilityWindow;
use App\Models\Session;
use App\Models\User;
use App\Services\Scheduling\Rules\AvailabilityRules;
use App\Services\Scheduling\Rules\TimezoneRules;
use App\Support\DbTime;
use Carbon\CarbonImmutable;

final class AvailabilitySlotsService
{
    /**
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable, timezone: string}>
     */
    public function forMentor(
        User $mentor,
        User $requester,
        string $from,
        string $to,
        int $durationMinutes,
    ): array {
        $timezone = TimezoneRules::resolveProfileTimezone(
            $requester->timezone,
            config('scheduling.default_timezone'),
        );
        $range = [
            'start' => CarbonImmutable::parse($from, $timezone)->utc(),
            'end' => CarbonImmutable::parse($to, $timezone)->utc(),
        ];

        $windows = MentorAvailabilityWindow::query()
            ->where('user_id', $mentor->id)
            ->get(['day_of_week', 'start_time', 'end_time', 'timezone'])
            ->map(fn (MentorAvailabilityWindow $window): array => [
                'day_of_week' => (int) $window->day_of_week,
                'start_time' => $window->start_time->format('H:i:s'),
                'end_time' => $window->end_time->format('H:i:s'),
                'timezone' => $window->timezone,
            ])
            ->all();

        $exceptions = MentorAvailabilityException::query()
            ->where('user_id', $mentor->id)
            ->get(['mode', 'start_date', 'end_date', 'start_time', 'end_time', 'timezone'])
            ->map(fn (MentorAvailabilityException $exception): array => $exception->toRuleArray())
            ->all();

        $busy = $this->busyIntervals($mentor, $requester, $range['start'], $range['end']);
        $slots = AvailabilityRules::freeSlots(
            $windows,
            $exceptions,
            $busy,
            $range,
            $durationMinutes,
            $timezone,
            now(),
        );

        return array_map(fn (array $slot): array => [
            ...$slot,
            'timezone' => $timezone,
        ], $slots);
    }

    /**
     * The booking relationship is not part of this read contract, so account for the
     * mentor and requester calendars here; booking revalidates every candidate participant.
     *
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function busyIntervals(
        User $mentor,
        User $requester,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $participantIds = [$mentor->id, $requester->id];
        $lookbackMinutes = (int) config('scheduling.max_duration_minutes', 480);

        $query = Session::query()
            ->whereNotIn('status', ['canceled', 'not_completed'])
            ->where('scheduled_at', '>=', DbTime::toRaw($from->subMinutes($lookbackMinutes)))
            ->where('scheduled_at', '<', DbTime::toRaw($to))
            ->whereRaw("scheduled_at + (duration_minutes * INTERVAL '1 minute') > ?", [DbTime::toRaw($from)])
            ->where(function ($query) use ($participantIds): void {
                $query->whereHas('relationship', fn ($relationship) => $relationship->whereIn('mentor_id', $participantIds))
                    ->orWhereHas('attendees', fn ($attendees) => $attendees->whereIn('user_id', $participantIds))
                    ->orWhereHas('relationship', function ($relationship) use ($participantIds): void {
                        $relationship->whereIn('source_type', ['user', User::class])
                            ->whereIn('source_id', $participantIds);
                    });
            });

        return $query->get(['scheduled_at', 'duration_minutes'])
            ->map(function (Session $session): array {
                // The sessions column is a timezone-less timestamp holding
                // app.timezone wall time; convert to the real UTC instant.
                $start = DbTime::fromRaw($session->getRawOriginal('scheduled_at'));

                return [
                    'start' => $start,
                    'end' => $start->addMinutes((int) $session->duration_minutes),
                ];
            })
            ->all();
    }
}
