<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    public function definition(): array
    {
        $filename = (string) Str::uuid() . '.pdf';

        return [
            'relationship_id' => MentorshipRelationship::factory(),
            'uploaded_by' => User::factory(),
            'purpose' => 'challenge_content',
            'disk' => 'local',
            'path' => "mentor/test/{$filename}",
            'original_name' => 'resource.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'metadata' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (MediaAsset $asset): void {
            $relationship = $asset->relationship()->first();
            if ($relationship?->source_type === 'user') {
                $asset->forceFill(['uploaded_by' => $relationship->mentor_id])->saveQuietly();
            }
        });
    }

    public function forRelationship(MentorshipRelationship $relationship): static
    {
        return $this->state([
            'relationship_id' => $relationship->id,
            'uploaded_by' => $relationship->mentor_id,
        ]);
    }
}
