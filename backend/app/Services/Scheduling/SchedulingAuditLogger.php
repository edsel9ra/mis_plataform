<?php

namespace App\Services\Scheduling;

use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;
use DateTimeInterface;

class SchedulingAuditLogger
{
    private const AUDITED_FIELDS = [
        'scheduled_at',
        'timezone',
        'duration_minutes',
        'session_type',
        'status',
        'canceled_at',
        'canceled_by',
        'cancellation_reason',
        'canceled_late',
        'cancellation_lead_minutes',
    ];

    public function snapshot(Session $session): array
    {
        $snapshot = [];

        foreach (self::AUDITED_FIELDS as $field) {
            $value = $session->getRawOriginal($field);

            if ($value === null) {
                $value = $session->getAttribute($field);
            }

            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $snapshot[$field] = $value;
        }

        return $snapshot;
    }

    public function record(
        Session $session,
        User $actor,
        string $operation,
        array $changedFields,
        array $before = [],
    ): void {
        $relationship = $session->relationLoaded('relationship')
            ? $session->relationship
            : $session->relationship()->first();

        if ($actor->isAdmin()) {
            $category = 'support';
        } elseif ($this->isCollectiveManager($actor, $relationship)) {
            $category = 'collective';
        } elseif ($operation === 'session.created') {
            $category = 'user';
        } else {
            return;
        }

        activity('scheduling')
            ->causedBy($actor)
            ->performedOn($session)
            ->event("{$category}.{$operation}")
            ->withProperties([
                'session_id' => $session->id,
                'changed_fields' => array_values(array_unique($changedFields)),
                'before' => $before,
                'after' => $this->snapshot($session),
                'occurred_at' => now()->toIso8601String(),
            ])
            ->log("{$category}.{$operation}");
    }

    private function isCollectiveManager(User $actor, ?MentorshipRelationship $relationship): bool
    {
        if (! $relationship) {
            return false;
        }

        $sourceType = MentorshipRelationship::canonicalSourceType($relationship->source_type);

        if (! in_array($sourceType, ['family_group', 'cohort', 'company'], true)) {
            return false;
        }

        return MentorshipRelationship::userCanManageSource(
            $actor,
            $sourceType,
            $relationship->source_id,
        );
    }
}
