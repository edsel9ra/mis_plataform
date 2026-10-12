<?php

namespace App\Actions;

use App\Models\Challenge;
use App\Models\MentorshipRelationship;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\PlanEntitlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignChallengeAction
{
    public function __construct(
        private PlanEntitlementService $entitlements,
    ) {}

    public function execute(
        User $mentor,
        User $mentee,
        MentorshipRelationship $relationship,
        Challenge $template,
        ?string $dueAt = null,
    ): Challenge {
        return DB::transaction(function () use ($mentor, $mentee, $relationship, $template, $dueAt) {
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

            if ($template->trashed() || !$template->isTemplate() || $template->mentor_id !== $mentor->id) {
                throw ValidationException::withMessages([
                    'challenge_id' => ['The selected challenge is not a catalog template.'],
                ]);
            }

            $template->loadMissing('mediaAssets');
            $access = $this->entitlements->forRelationship($relationship);
            $maximum = $this->entitlements->value($access['plan'], 'max_active_challenges');
            if (is_numeric($maximum) && Challenge::query()
                ->where('relationship_id', $relationship->id)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->count() >= (int) $maximum) {
                throw ValidationException::withMessages([
                    'challenge' => ['The active challenge limit for this plan has been reached.'],
                ]);
            }

            $assignment = Challenge::create([
                'template_id' => $template->id,
                'relationship_id' => $relationship->id,
                'mentor_id' => $mentor->id,
                'mentee_id' => $mentee->id,
                'title' => $template->title,
                'description' => $template->description,
                'instructions' => $template->instructions,
                'objective' => $template->objective,
                'success_criteria' => $template->success_criteria,
                'presentation_format' => $template->presentation_format,
                'frequency' => $template->frequency,
                'progress' => $template->progress,
                'status' => 'assigned',
                'due_at' => $dueAt ?? $template->due_at,
            ]);

            foreach ($template->mediaAssets as $asset) {
                $copy = new MediaAsset([
                    'relationship_id' => $relationship->id,
                    'uploaded_by' => $mentor->id,
                    'purpose' => 'challenge_content',
                    'disk' => $asset->disk,
                    'path' => $asset->path,
                    'original_name' => $asset->original_name,
                    'mime_type' => $asset->mime_type,
                    'size' => $asset->size,
                    'metadata' => $asset->metadata,
                ]);
                $copy->mediaable()->associate($assignment);
                $copy->save();
            }

            return $assignment->load('mediaAssets');
        });
    }
}
