<?php

namespace App\Actions;

use App\Models\Challenge;
use App\Models\MentorshipRelationship;
use App\Models\User;
use App\Services\PlanEntitlementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateChallengeAction
{
    public function __construct(
        private StoreMediaAssetAction $media,
        private PlanEntitlementService $entitlements,
    ) {}

    /** @param array<string, mixed> $data @param array<int, UploadedFile> $files */
    public function execute(
        User $mentor,
        User $mentee,
        MentorshipRelationship $relationship,
        array $data,
        array $files = [],
    ): Challenge {
        return DB::transaction(function () use ($mentor, $mentee, $relationship, $data, $files) {
            $relationship = MentorshipRelationship::query()
                ->lockForUpdate()
                ->findOrFail($relationship->id);

            if ($relationship->status !== 'active'
                || $relationship->mentor_id !== $mentor->id
                || MentorshipRelationship::canonicalSourceType($relationship->source_type) !== 'user'
                || $relationship->source_id !== $mentee->id
            ) {
                throw ValidationException::withMessages([
                    'relationship_id' => ['The mentorship relationship is not available.'],
                ]);
            }

            $access = $this->entitlements->forRelationship($relationship);
            $maximum = $this->entitlements->value($access['plan'], 'max_active_challenges');

            if (is_numeric($maximum)) {
                $activeCount = Challenge::query()
                ->where('relationship_id', $relationship->id)
                ->whereIn('status', ['assigned', 'in_progress'])
                    ->count();

                if ($activeCount >= (int) $maximum) {
                    throw ValidationException::withMessages([
                        'challenge' => ['The active challenge limit for this plan has been reached.'],
                    ]);
                }
            }

            $challenge = Challenge::create([
                'relationship_id' => $relationship->id,
                'mentor_id' => $mentor->id,
                'mentee_id' => $mentee->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'objective' => $data['objective'] ?? null,
                'success_criteria' => $data['success_criteria'] ?? null,
                'presentation_format' => $data['presentation_format'] ?? null,
                'frequency' => $data['frequency'] ?? null,
                'progress' => $data['progress'] ?? null,
                'status' => 'assigned',
                'due_at' => $data['due_at'] ?? null,
            ]);

            foreach ($files as $file) {
                $this->media->execute($file, $mentor, $relationship, 'challenge_content', $challenge);
            }

            return $challenge->load('mediaAssets');
        });
    }
}
