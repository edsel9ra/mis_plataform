<?php

namespace App\Actions;

use App\Models\Challenge;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateChallengeTemplateAction
{
    public function __construct(
        private StoreMediaAssetAction $media,
    ) {}

    /** @param array<string, mixed> $data @param array<int, UploadedFile> $files */
    public function execute(User $mentor, array $data, array $files = []): Challenge
    {
        return DB::transaction(function () use ($mentor, $data, $files) {
            $template = Challenge::create([
                'relationship_id' => null,
                'mentor_id' => $mentor->id,
                'mentee_id' => null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'objective' => $data['objective'] ?? null,
                'success_criteria' => $data['success_criteria'] ?? null,
                'presentation_format' => $data['presentation_format'] ?? null,
                'frequency' => $data['frequency'] ?? null,
                'progress' => $data['progress'] ?? null,
                'status' => 'template',
                'due_at' => $data['due_at'] ?? null,
            ]);

            foreach ($files as $file) {
                $this->media->execute($file, $mentor, null, 'challenge_content', $template);
            }

            return $template->load('mediaAssets');
        });
    }
}
