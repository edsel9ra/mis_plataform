<?php

namespace App\Services\Scheduling\Rules;

use App\Enums\AttendanceDecision;
use App\Enums\AttendanceRelation;
use App\Enums\SessionStatus;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/** Pure window and precedence rules for individual session attendance. */
final class AttendanceRules
{
    private const int DEFAULT_WINDOW_HOURS = 48;

    public static function windowEnd(DateTimeInterface $endAt, int $windowHours = self::DEFAULT_WINDOW_HOURS): DateTimeImmutable
    {
        return self::utc($endAt)->add(new DateInterval("PT{$windowHours}H"));
    }

    /**
     * Evaluate a proposed confirmation using facts loaded by the caller.
     * Session and attendee timestamp facts must represent UTC instants.
     *
     * @param array{status: string|SessionStatus, end_at: DateTimeInterface|string|null, excluded_user_ids?: list<string>} $session
     * @param array{user_id: string, created_at?: DateTimeInterface|string|null, left_at?: DateTimeInterface|string|null, confirmed_by?: string|null}|null $attendee
     * @param list<string> $chargeableTargetIds
     */
    public static function evaluate(
        array $session,
        ?array $attendee,
        string $actorId,
        string $targetId,
        AttendanceRelation $actorRelation,
        array $chargeableTargetIds,
        DateTimeInterface $now,
        int $windowHours = self::DEFAULT_WINDOW_HOURS,
    ): AttendanceDecision {
        return self::assess(
            $session,
            $attendee,
            $actorId,
            $targetId,
            $actorRelation,
            $chargeableTargetIds,
            $now,
            $windowHours,
        )[0];
    }

    /**
     * Same evaluation as {@see evaluate()} plus the blocking reason.
     *
     * @param array{status: string|SessionStatus, end_at: DateTimeInterface|string|null, excluded_user_ids?: list<string>} $session
     * @param array{user_id: string, created_at?: DateTimeInterface|string|null, left_at?: DateTimeInterface|string|null, confirmed_by?: string|null}|null $attendee
     * @param list<string> $chargeableTargetIds
     * @return array{0: AttendanceDecision, 1: ?string}
     */
    public static function assess(
        array $session,
        ?array $attendee,
        string $actorId,
        string $targetId,
        AttendanceRelation $actorRelation,
        array $chargeableTargetIds,
        DateTimeInterface $now,
        int $windowHours = self::DEFAULT_WINDOW_HOURS,
    ): array {
        if ($attendee === null || (string) $attendee['user_id'] !== $targetId) {
            return [AttendanceDecision::Reject, BlockReason::NOT_REGISTERED];
        }

        if (in_array(self::statusValue($session['status']), [
            SessionStatus::Canceled->value,
            SessionStatus::NotCompleted->value,
        ], true)) {
            return [AttendanceDecision::Reject, BlockReason::TERMINAL_STATUS];
        }

        $endAt = self::instant($session['end_at'] ?? null);
        $createdAt = self::instant($attendee['created_at'] ?? null);
        $leftAt = self::instant($attendee['left_at'] ?? null);

        if ($endAt === null || $createdAt === null || $createdAt > $endAt) {
            return [AttendanceDecision::Reject, BlockReason::NOT_REGISTERED];
        }

        if ($leftAt !== null && $leftAt <= $endAt) {
            return [AttendanceDecision::Reject, BlockReason::NOT_REGISTERED];
        }

        if (in_array($targetId, $session['excluded_user_ids'] ?? [], true)) {
            return [AttendanceDecision::Reject, BlockReason::EXCLUDED];
        }

        $now = self::utc($now);

        if ($now < $endAt) {
            return [AttendanceDecision::Reject, BlockReason::NOT_ENDED];
        }

        if ($now >= self::windowEnd($endAt, $windowHours)) {
            return [AttendanceDecision::Reject, BlockReason::WINDOW_CLOSED];
        }

        if ($actorId === $targetId) {
            return [AttendanceDecision::Update, null];
        }

        if ($actorRelation === AttendanceRelation::Mentor) {
            return [AttendanceDecision::Reject, BlockReason::FORBIDDEN];
        }

        if ($actorRelation !== AttendanceRelation::Responsible) {
            return [AttendanceDecision::Reject, BlockReason::FORBIDDEN];
        }

        if (! in_array($targetId, $chargeableTargetIds, true)) {
            return [AttendanceDecision::Reject, BlockReason::NOT_CHARGEABLE];
        }

        if (($attendee['confirmed_by'] ?? null) === $targetId) {
            return [AttendanceDecision::Keep, BlockReason::ALREADY_CONFIRMED_BY_MEMBER];
        }

        return [AttendanceDecision::Update, null];
    }

    private static function statusValue(string|SessionStatus $status): string
    {
        return $status instanceof SessionStatus ? $status->value : $status;
    }

    private static function instant(DateTimeInterface|string|null $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $instant = $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : new DateTimeImmutable($value, new DateTimeZone('UTC'));

        return self::utc($instant);
    }

    private static function utc(DateTimeInterface $dateTime): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($dateTime)
            ->setTimezone(new DateTimeZone('UTC'));
    }
}
