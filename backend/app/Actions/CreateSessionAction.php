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
use App\Services\ContentAccessService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSessionAction
{
    public function __construct(
        private ContentAccessService $contentAccess,
    ) {}

    public function execute(array $data): Session
    {
        return DB::transaction(function () use ($data) {
            $relationship = MentorshipRelationship::query()
                ->lockForUpdate()
                ->findOrFail($data['relationship_id']);

            if ($relationship->status !== 'active') {
                throw ValidationException::withMessages([
                    'relationship_id' => [__('sessions.relationship_inactive')],
                ]);
            }

            $expectedType = $this->expectedSessionType($relationship->type);
            if ($expectedType === null || $expectedType !== $data['session_type']) {
                throw ValidationException::withMessages([
                    'session_type' => [__('sessions.type_mismatch')],
                ]);
            }

            $source = $this->lockSource($relationship);
            $plan = $this->planForSource($relationship, $source);

            if ($plan && $this->monthlySessionCount($relationship, Carbon::parse($data['scheduled_at'])) >= $plan->max_sessions_per_month) {
                throw ValidationException::withMessages([
                    'session' => [__('sessions.plan_limit_reached')],
                ]);
            }

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
            $employeeIds = Employee::where('company_id', $relationship->source_id)
                ->where('status', 'active')
                ->pluck('user_id')
                ->all();

            $attendeeIds->push($companyAdminId)->push(...$employeeIds);
        }

        return $attendeeIds
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function expectedSessionType(string $relationshipType): ?string
    {
        return match ($relationshipType) {
            'personal' => 'individual',
            'familiar' => 'family',
            'grupal' => 'group',
            'empresa' => 'corporate',
            default => null,
        };
    }

    private function lockSource(MentorshipRelationship $relationship): mixed
    {
        return match ($relationship->source_type) {
            'user', User::class => User::query()->lockForUpdate()->findOrFail($relationship->source_id),
            'family_group', FamilyGroup::class => FamilyGroup::query()->lockForUpdate()->findOrFail($relationship->source_id),
            'cohort', Cohort::class => Cohort::query()->lockForUpdate()->findOrFail($relationship->source_id),
            'company', Company::class => Company::query()->lockForUpdate()->findOrFail($relationship->source_id),
            default => throw ValidationException::withMessages([
                'relationship_id' => [__('sessions.source_unavailable')],
            ]),
        };
    }

    private function planForSource(MentorshipRelationship $relationship, mixed $source): mixed
    {
        return $this->contentAccess->activePlanForSource(
            $relationship->source_type,
            $relationship->source_id,
            $relationship->type,
        );
    }

    private function monthlySessionCount(MentorshipRelationship $relationship, Carbon $scheduledAt): int
    {
        return Session::query()
            ->whereHas('relationship', function ($query) use ($relationship) {
                $query->whereIn('source_type', $this->sourceTypes($relationship->source_type))
                    ->where('source_id', $relationship->source_id);
            })
            ->whereBetween('scheduled_at', [$scheduledAt->copy()->startOfMonth(), $scheduledAt->copy()->endOfMonth()])
            ->where('status', '!=', 'canceled')
            ->count();
    }

    private function sourceTypes(string $sourceType): array
    {
        return match ($sourceType) {
            'user', User::class => ['user', User::class],
            'family_group', FamilyGroup::class => ['family_group', FamilyGroup::class],
            'cohort', Cohort::class => ['cohort', Cohort::class],
            'company', Company::class => ['company', Company::class],
            default => [$sourceType],
        };
    }
}
