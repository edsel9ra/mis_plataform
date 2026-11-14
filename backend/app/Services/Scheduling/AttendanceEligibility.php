<?php

namespace App\Services\Scheduling;

use App\Enums\AttendanceRelation;
use App\Models\Employee;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;
use App\Services\Scheduling\Rules\ParticipantRules;

/**
 * Shared attendance eligibility facts: who the actor is relative to a
 * confirmation target and which targets a collective responsible may charge.
 */
final class AttendanceEligibility
{
    public function actorRelation(
        User $actor,
        string $targetUserId,
        MentorshipRelationship $relationship,
    ): AttendanceRelation {
        if ((string) $actor->id === $targetUserId) {
            return AttendanceRelation::Participant;
        }

        if ($actor->isAdmin()) {
            return AttendanceRelation::Other;
        }

        if ($relationship->mentor_id === $actor->id) {
            return AttendanceRelation::Mentor;
        }

        $sourceType = MentorshipRelationship::canonicalSourceType($relationship->source_type);

        if (in_array($sourceType, ['family_group', 'cohort', 'company'], true)
            && MentorshipRelationship::userCanManageSource($actor, $sourceType, $relationship->source_id)) {
            return AttendanceRelation::Responsible;
        }

        return AttendanceRelation::Other;
    }

    /** @return list<string> */
    public function chargeableTargetIds(MentorshipRelationship $relationship, Session $session): array
    {
        $sourceType = MentorshipRelationship::canonicalSourceType($relationship->source_type);
        $attendeeIds = $session->attendees()
            ->pluck('user_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        if ($sourceType === 'company') {
            $employeeIds = Employee::query()
                ->where('company_id', $relationship->source_id)
                ->whereIn('user_id', $attendeeIds)
                ->pluck('user_id')
                ->map(static fn ($id): string => (string) $id)
                ->all();

            return ParticipantRules::chargeableMembers($sourceType, $employeeIds, []);
        }

        $members = match ($sourceType) {
            'family_group' => FamilyMember::query()
                ->where('family_group_id', $relationship->source_id)
                ->whereIn('user_id', $attendeeIds)
                ->orderBy('id')
                ->get(['user_id', 'age']),
            'cohort' => FamilyMember::query()
                ->whereIn('user_id', $attendeeIds)
                ->orderBy('id')
                ->get(['user_id', 'age']),
            default => collect(),
        };
        $agesByUserId = [];

        foreach ($members as $member) {
            $agesByUserId[(string) $member->user_id][] = $member->age;
        }

        $ageByUserId = [];
        foreach ($agesByUserId as $userId => $ages) {
            $knownAges = array_values(array_unique(array_filter(
                $ages,
                static fn ($age): bool => is_int($age)
                    ? $age >= 0
                    : is_string($age) && ctype_digit($age),
            )));
            $ageByUserId[$userId] = count($knownAges) === 1 ? $knownAges[0] : null;
        }

        return ParticipantRules::chargeableMembers($sourceType, $attendeeIds, $ageByUserId);
    }
}
