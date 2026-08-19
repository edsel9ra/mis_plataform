<?php

namespace App\Actions;

use App\Models\MentorshipRelationship;
use App\Models\User;
use App\Services\MentorAssignmentService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRelationshipAction
{
    public function execute(array $data): MentorshipRelationship
    {
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
}
