<?php

namespace App\Services\Scheduling;

use App\Models\Cohort;
use App\Models\Company;
use App\Models\FamilyGroup;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\SessionParticipantExclusion;
use App\Models\User;
use App\Services\Scheduling\Rules\JoinDecision;
use App\Services\Scheduling\Rules\ParticipantRules;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SessionParticipantSyncService
{
    public function onMembershipChange(Model $source, User $user, string $change, Carbon $now): void
    {
        if (! in_array($change, ['added', 'removed'], true)) {
            throw new InvalidArgumentException("Unsupported membership change [{$change}].");
        }

        $sourceType = $this->sourceType($source);

        DB::transaction(function () use ($source, $sourceType, $user, $change, $now): void {
            $source::query()
                ->lockForUpdate()
                ->findOrFail($source->getKey());

            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            if ($change === 'added') {
                $this->addToUpcomingSessions($sourceType, (string) $source->getKey(), $lockedUser, $now);

                return;
            }

            $this->leaveUpcomingSessions($sourceType, (string) $source->getKey(), $lockedUser, $now);
        });
    }

    private function addToUpcomingSessions(string $sourceType, string $sourceId, User $user, Carbon $now): void
    {
        $sessions = $this->collectiveSessions($sourceType, $sourceId);
        if ($sessions->isEmpty()) {
            return;
        }

        $ownSessions = $this->ownSessions($user, $now, $sessions)
            ->map(fn (Session $ownSession): array => $this->sessionRuleData($ownSession))
            ->all();

        foreach ($sessions as $session) {
            if (SessionParticipantExclusion::query()
                ->where('session_id', $session->id)
                ->where('user_id', $user->id)
                ->exists()) {
                continue;
            }

            $decision = ParticipantRules::canJoinAfterBooking(
                $this->sessionRuleData($session),
                $ownSessions,
                $now,
            );

            if ($decision === JoinDecision::NotEligible) {
                continue;
            }

            if ($decision === JoinDecision::ExcludedForSession) {
                SessionParticipantExclusion::query()->firstOrCreate(
                    [
                        'session_id' => $session->id,
                        'user_id' => $user->id,
                    ],
                    ['excluded_at' => $now],
                );

                continue;
            }

            $attendee = SessionAttendee::query()
                ->where('session_id', $session->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($attendee !== null) {
                if ($attendee->left_at !== null) {
                    $attendee->forceFill(['left_at' => null])->save();
                }

                continue;
            }

            SessionAttendee::create([
                'session_id' => $session->id,
                'user_id' => $user->id,
            ]);
        }
    }

    private function leaveUpcomingSessions(string $sourceType, string $sourceId, User $user, Carbon $now): void
    {
        $sessions = $this->collectiveSessions($sourceType, $sourceId);

        foreach ($sessions as $session) {
            $endAt = $session->scheduled_at->copy()->addMinutes((int) $session->duration_minutes);
            if ($endAt->lessThan($now)) {
                continue;
            }

            $attendee = SessionAttendee::query()
                ->where('session_id', $session->id)
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if ($attendee === null) {
                continue;
            }

            $attendee->forceFill(['left_at' => $now])->save();
        }
    }

    private function collectiveSessions(string $sourceType, string $sourceId): Collection
    {
        return Session::query()
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->whereHas('relationship', function ($relationship) use ($sourceType, $sourceId): void {
                $relationship
                    ->whereIn('source_type', $this->sourceTypeAliases($sourceType))
                    ->where('source_id', $sourceId);
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function ownSessions(User $user, Carbon $now, Collection $collectiveSessions): Collection
    {
        $starts = $collectiveSessions->map(
            fn (Session $session): Carbon => Carbon::parse($session->scheduled_at),
        );
        $ends = $collectiveSessions->map(
            fn (Session $session): Carbon => Carbon::parse($session->scheduled_at)
                ->addMinutes((int) $session->duration_minutes),
        );
        $earliestStart = $starts->sortBy(fn (Carbon $start): int => $start->getTimestamp())->first();
        $latestEnd = $ends->sortBy(fn (Carbon $end): int => $end->getTimestamp())->last();

        return Session::query()
            ->whereNotIn('status', ['canceled', 'not_completed'])
            ->where('scheduled_at', '<', $latestEnd)
            ->whereRaw("scheduled_at + (duration_minutes * INTERVAL '1 minute') > ?", [$earliestStart])
            ->where(function ($query) use ($user, $now): void {
                $query->whereHas('attendees', function ($attendees) use ($user, $now): void {
                    $attendees
                        ->where('user_id', $user->id)
                        ->where(function ($activeAttendee) use ($now): void {
                            $activeAttendee
                                ->whereNull('left_at')
                                ->orWhere('left_at', '>', $now);
                        });
                })
                    ->orWhereHas('relationship', function ($relationship) use ($user): void {
                        $relationship
                            ->where('mentor_id', $user->id)
                            ->orWhere(function ($source) use ($user): void {
                                $source
                                    ->whereIn('source_type', ['user', User::class])
                                    ->where('source_id', $user->id);
                            });
                    });
            })
            ->with('relationship:id,source_type,source_id,mentor_id')
            ->get();
    }

    private function sessionRuleData(Session $session): array
    {
        $start = Carbon::parse($session->scheduled_at);

        return [
            'start' => $start,
            'end' => $start->copy()->addMinutes((int) $session->duration_minutes),
            'status' => (string) $session->status,
        ];
    }

    private function sourceType(Model $source): string
    {
        return match (true) {
            $source instanceof FamilyGroup => 'family_group',
            $source instanceof Cohort => 'cohort',
            $source instanceof Company => 'company',
            default => throw new InvalidArgumentException('Membership synchronization requires a collective source.'),
        };
    }

    private function sourceTypeAliases(string $sourceType): array
    {
        return match ($sourceType) {
            'family_group' => ['family_group', FamilyGroup::class],
            'cohort' => ['cohort', Cohort::class],
            'company' => ['company', Company::class],
        };
    }
}
