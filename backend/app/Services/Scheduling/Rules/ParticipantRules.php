<?php

namespace App\Services\Scheduling\Rules;

use DateTimeImmutable;
use DateTimeInterface;

/** Pure rules for prospective collective participation and its end-time anchor. */
final class ParticipantRules
{
    /**
     * Decide whether a newly-added member joins this already-booked collective session.
     *
     * @param array{start: DateTimeInterface, end: DateTimeInterface, status: string} $session
     * @param list<array{start: DateTimeInterface, end: DateTimeInterface, status: string}> $ownSessions
     */
    public static function canJoinAfterBooking(
        array $session,
        array $ownSessions,
        DateTimeInterface $now,
    ): JoinDecision {
        if (! in_array($session['status'], ['scheduled', 'in_progress'], true)
            || DateTimeImmutable::createFromInterface($now) >= DateTimeImmutable::createFromInterface($session['end'])) {
            return JoinDecision::NotEligible;
        }

        foreach ($ownSessions as $ownSession) {
            if (in_array($ownSession['status'], ['canceled', 'not_completed'], true)) {
                continue;
            }

            if (OverlapDetector::overlaps(
                $session['start'],
                $session['end'],
                $ownSession['start'],
                $ownSession['end'],
            )) {
                return JoinDecision::ExcludedForSession;
            }
        }

        return JoinDecision::Join;
    }

    /**
     * Resolve the immutable participant set at the scheduled end-time anchor.
     *
     * `$now` is explicit so callers remain deterministic; eligibility is anchored to `$endAt`,
     * not recalculated from the time at which this historical set is read.
     *
     * @param array{mentor_id: string, excluded_user_ids?: list<string>} $session
     * @param list<array{user_id: string, created_at: DateTimeInterface, left_at: DateTimeInterface|null}> $attendees
     * @return list<string>
     */
    public static function participantsAtEnd(
        array $session,
        array $attendees,
        DateTimeInterface $endAt,
        DateTimeInterface $now,
    ): array {
        $anchor = DateTimeImmutable::createFromInterface($endAt);
        $excludedIds = $session['excluded_user_ids'] ?? [];
        $participants = [];

        foreach ($attendees as $attendee) {
            $userId = (string) $attendee['user_id'];
            $joinedAt = DateTimeImmutable::createFromInterface($attendee['created_at']);
            $leftAt = $attendee['left_at'] === null
                ? null
                : DateTimeImmutable::createFromInterface($attendee['left_at']);

            if ($joinedAt <= $anchor
                && ($leftAt === null || $leftAt > $anchor)
                && ! in_array($userId, $excludedIds, true)
                && ! in_array($userId, $participants, true)) {
                $participants[] = $userId;
            }
        }

        $mentorId = (string) $session['mentor_id'];
        if (! in_array($mentorId, $participants, true)) {
            $participants[] = $mentorId;
        }

        return $participants;
    }

    /**
     * Resolve the members a collective's responsible may confirm for.
     * Company members are expected to be the already-filtered employee records;
     * family/cohort eligibility requires a known age below 18.
     *
     * @param list<string> $memberIds
     * @param array<string, int|string|null> $ageByUserId
     * @return list<string>
     */
    public static function chargeableMembers(string $sourceType, array $memberIds, array $ageByUserId): array
    {
        $memberIds = array_values(array_unique(array_map('strval', $memberIds)));

        if ($sourceType === 'company') {
            return $memberIds;
        }

        if (! in_array($sourceType, ['family_group', 'cohort'], true)) {
            return [];
        }

        return array_values(array_filter($memberIds, static function (string $userId) use ($ageByUserId): bool {
            $age = $ageByUserId[$userId] ?? null;

            if (! is_int($age) && (! is_string($age) || ! ctype_digit($age))) {
                return false;
            }

            $age = (int) $age;

            return $age >= 0 && $age < 18;
        }));
    }
}
