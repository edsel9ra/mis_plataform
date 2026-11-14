<?php

namespace App\Services\Scheduling\Rules;

use App\Models\Session;
use App\Support\DbTime;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class MeetAutomationRules
{
    public static function eligible(Session $session, DateTimeInterface $now): bool
    {
        if ($session->status !== 'scheduled' || $session->meet_link || $session->meet_event_id) {
            return false;
        }

        $scheduledAt = $session->getAttributes()['scheduled_at'] ?? null;

        return $scheduledAt !== null
            && DbTime::fromRaw($scheduledAt)->greaterThan(CarbonImmutable::instance($now)->utc());
    }

    public static function needsScheduleSynchronization(Session $session, DateTimeInterface $now): bool
    {
        $scheduledAt = $session->getAttributes()['scheduled_at'] ?? null;

        if (
            $session->status !== 'scheduled'
            || $session->meet_event_id === null
            || $scheduledAt === null
            || ! DbTime::fromRaw($scheduledAt)->greaterThan(CarbonImmutable::instance($now)->utc())
        ) {
            return false;
        }

        return ! hash_equals(
            (string) ($session->meet_synced_schedule_hash ?? ''),
            self::scheduleFingerprint($session),
        );
    }

    public static function scheduleFingerprint(Session $session): string
    {
        $scheduledAt = DbTime::fromRaw($session->getAttributes()['scheduled_at'])
            ->format('Y-m-d\TH:i:s.u\Z');

        return hash('sha256', json_encode([
            'title' => (string) $session->title,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => (int) $session->duration_minutes,
            'timezone' => (string) $session->timezone,
        ], JSON_THROW_ON_ERROR));
    }
}
