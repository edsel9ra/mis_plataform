<?php

namespace App\Actions;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateChallengeSubmissionAction
{
    public function __construct(
        private StoreMediaAssetAction $media,
    ) {}

    /** @param array<string, mixed> $data @param array<int, UploadedFile> $files */
    public function execute(
        User $mentee,
        MentorshipRelationship $relationship,
        Challenge $challenge,
        array $data,
        array $files = [],
        ?User $uploader = null,
    ): ChallengeSubmission {
        return DB::transaction(function () use ($mentee, $relationship, $challenge, $data, $files, $uploader) {
            $submission = ChallengeSubmission::create([
                'challenge_id' => $challenge->id,
                'relationship_id' => $relationship->id,
                'mentee_id' => $mentee->id,
                'note' => $data['note'] ?? null,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            foreach ($files as $file) {
                $this->media->execute($file, $uploader ?? $mentee, $relationship, 'challenge_submission', $submission);
            }

            return $submission->load('mediaAssets');
        });
    }
}
