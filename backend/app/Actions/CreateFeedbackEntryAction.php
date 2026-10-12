<?php

namespace App\Actions;

use App\Models\Challenge;
use App\Models\FeedbackEntry;
use App\Models\FeedbackThread;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateFeedbackEntryAction
{
    public function __construct(
        private StoreMediaAssetAction $media,
    ) {}

    /** @param array<string, mixed> $data @param array<int, UploadedFile> $files */
    public function execute(
        User $mentor,
        User $mentee,
        MentorshipRelationship $relationship,
        ?Challenge $challenge,
        array $data,
        array $files = [],
    ): FeedbackEntry {
        return DB::transaction(function () use ($mentor, $mentee, $relationship, $challenge, $data, $files) {
            $relationship = MentorshipRelationship::query()
                ->lockForUpdate()
                ->findOrFail($relationship->id);

            $threadQuery = FeedbackThread::query()->where('relationship_id', $relationship->id);
            $challenge
                ? $threadQuery->where('challenge_id', $challenge->id)
                : $threadQuery->whereNull('challenge_id');
            $threadQuery->lockForUpdate();
            $thread = $threadQuery->first();
            if (!$thread) {
                $thread = FeedbackThread::create([
                    'relationship_id' => $relationship->id,
                    'challenge_id' => $challenge?->id,
                    'mentor_id' => $mentor->id,
                    'mentee_id' => $mentee->id,
                    'status' => 'open',
                ]);
            }

            $entry = FeedbackEntry::create([
                'feedback_thread_id' => $thread->id,
                'author_id' => $mentor->id,
                'body' => $data['body'] ?? null,
            ]);

            foreach ($files as $file) {
                $this->media->execute($file, $mentor, $relationship, 'mentor_feedback', $entry);
            }

            return $entry->load(['author', 'mediaAssets', 'thread']);
        });
    }
}
