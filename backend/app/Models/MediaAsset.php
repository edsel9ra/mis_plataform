<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MediaAsset extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'relationship_id',
        'uploaded_by',
        'mediaable_type',
        'mediaable_id',
        'purpose',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(MentorshipRelationship::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function mediaable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Objective-evidence assets whose upload was never attached to an
     * evidence, or whose evidence row disappeared through a cascade, older
     * than the cutoff (RF-020.10).
     */
    public function scopeOrphanEvidence(Builder $query, CarbonInterface $cutoff): Builder
    {
        $evidenceMorph = (new ObjectiveEvidence)->getMorphClass();

        return $query
            ->where('purpose', 'objective_evidence')
            ->where('created_at', '<', $cutoff)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('objective_evidences')
                    ->whereColumn('objective_evidences.media_asset_id', 'media_assets.id');
            })
            ->where(function ($query) use ($evidenceMorph) {
                $query->where('mediaable_type', '!=', $evidenceMorph)
                    ->orWhereNull('mediaable_type')
                    ->orWhereNotExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('objective_evidences')
                            ->whereColumn('objective_evidences.id', 'media_assets.mediaable_id');
                    });
            });
    }

    /**
     * Same attachability check as StoreObjectiveEvidenceRequest: the upload
     * is an objective evidence pointing at this objective, was uploaded by
     * the actor and is not linked to another evidence yet.
     */
    public function isAttachableAsEvidence(Objective $objective, User $actor): bool
    {
        return $this->purpose === 'objective_evidence'
            && $this->mediaable instanceof Objective
            && $this->mediaable->id === $objective->id
            && $this->uploaded_by === $actor->id
            && ! ObjectiveEvidence::where('media_asset_id', $this->id)->exists();
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('media.show', ['media' => $this->id], false);
    }
}
