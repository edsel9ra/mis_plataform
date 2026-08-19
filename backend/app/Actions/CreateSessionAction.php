<?php

namespace App\Actions;

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateSessionAction
{
    public function execute(array $data): Session
    {
        return DB::transaction(function () use ($data) {
            $relationship = MentorshipRelationship::query()
                ->lockForUpdate()
                ->findOrFail($data['relationship_id']);

            $session = Session::create([
                'relationship_id' => $data['relationship_id'],
                'session_type' => $data['session_type'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'scheduled_at' => $data['scheduled_at'],
                'duration_minutes' => $data['duration_minutes'],
                'status' => 'scheduled',
            ]);

            foreach ($this->attendeeIdsForRelationship($relationship) as $userId) {
                SessionAttendee::create([
                    'session_id' => $session->id,
                    'user_id' => $userId,
                ]);
            }

            return $session->load(['relationship.mentor', 'attendees.user']);
        });
    }

    private function attendeeIdsForRelationship(MentorshipRelationship $relationship): array
    {
        $attendeeIds = collect([$relationship->mentor_id]);

        if (in_array($relationship->source_type, ['user', User::class], true)) {
            $attendeeIds->push($relationship->source_id);
        }

        if (in_array($relationship->source_type, ['family_group', FamilyGroup::class], true)) {
            $familyHeadId = FamilyGroup::where('id', $relationship->source_id)->value('head_user_id');
            $familyMemberIds = FamilyMember::where('family_group_id', $relationship->source_id)
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->all();

            $attendeeIds->push($familyHeadId)->push(...$familyMemberIds);
        }

        if (in_array($relationship->source_type, ['cohort', Cohort::class], true)) {
            $attendeeIds->push(...CohortMember::where('cohort_id', $relationship->source_id)
                ->where('status', 'active')
                ->pluck('user_id')
                ->all());
        }

        if (in_array($relationship->source_type, ['company', Company::class], true)) {
            $companyAdminId = Company::where('id', $relationship->source_id)->value('admin_id');
            $employeeIds = Employee::where('company_id', $relationship->source_id)->pluck('user_id')->all();

            $attendeeIds->push($companyAdminId)->push(...$employeeIds);
        }

        return $attendeeIds
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
