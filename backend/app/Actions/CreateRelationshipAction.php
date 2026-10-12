<?php

namespace App\Actions;

use App\Models\Cohort;
use App\Models\Company;
use App\Models\FamilyGroup;
use App\Models\MentorshipRelationship;
use App\Models\User;
use App\Services\ContentAccessService;
use App\Services\MentorAssignmentService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRelationshipAction
{
    public function __construct(
        private ContentAccessService $contentAccess,
    ) {}

    public function execute(array $data): MentorshipRelationship
    {
        $data['source_type'] = MentorshipRelationship::canonicalSourceType($data['source_type']);

        try {
            return DB::transaction(function () use ($data) {
                $mentor = User::query()->lockForUpdate()->findOrFail($data['mentor_id']);
                if ($mentor->role !== 'mentor' || !$mentor->is_active) {
                    throw ValidationException::withMessages([
                        'mentor_id' => [__('validation.mentor_invalid')],
                    ]);
                }

                $expectedSourceType = MentorAssignmentService::expectedSourceType($data['type']);
                if ($expectedSourceType !== $data['source_type']) {
                    throw ValidationException::withMessages([
                        'source_type' => [__('validation.relationship_source_type_invalid')],
                    ]);
                }

                if ($mentor->client_type !== $data['type']) {
                    throw ValidationException::withMessages([
                        'mentor_id' => [__('validation.mentor_client_type_invalid')],
                    ]);
                }

                $source = $this->lockSource($data['source_type'], $data['source_id']);
                $plan = $this->contentAccess->activePlanForSource(
                    $data['source_type'],
                    $data['source_id'],
                    $data['type'],
                );

                if ($plan && MentorshipRelationship::query()
                    ->where('source_type', $data['source_type'])
                    ->where('source_id', $data['source_id'])
                    ->whereIn('status', ['pending', 'active'])
                    ->count() >= $plan->max_mentors) {
                    throw ValidationException::withMessages([
                        'mentor_id' => [__('relationships.mentor_limit_reached')],
                    ]);
                }

                $existing = MentorshipRelationship::where([
                    'source_type' => $data['source_type'],
                    'source_id' => $data['source_id'],
                    'mentor_id' => $data['mentor_id'],
                ])->whereIn('status', ['pending', 'active'])->first();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'relationship' => [__('validation.relationship_exists')],
                    ]);
                }

                return MentorshipRelationship::create([
                    'type' => $data['type'],
                    'source_type' => $data['source_type'],
                    'source_id' => $data['source_id'],
                    'mentor_id' => $data['mentor_id'],
                    'objectives' => $data['objectives'] ?? null,
                    'status' => 'pending',
                ])->load(['mentor', 'source']);
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? $exception->getCode()) === '23505') {
                throw ValidationException::withMessages([
                    'relationship' => [__('validation.relationship_exists')],
                ]);
            }

            throw $exception;
        }
    }

    private function lockSource(string $sourceType, string $sourceId): User|FamilyGroup|Cohort|Company
    {
        return match ($sourceType) {
            'user', User::class => User::query()->lockForUpdate()->findOrFail($sourceId),
            'family_group', FamilyGroup::class => FamilyGroup::query()->lockForUpdate()->findOrFail($sourceId),
            'cohort', Cohort::class => Cohort::query()->lockForUpdate()->findOrFail($sourceId),
            'company', Company::class => Company::query()->lockForUpdate()->findOrFail($sourceId),
            default => throw ValidationException::withMessages([
                'source_type' => [__('validation.relationship_source_type_invalid')],
            ]),
        };
    }
}
