<?php

namespace App\Models;

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

    public function getDownloadUrlAttribute(): string
    {
        return route('media.show', ['media' => $this->id], false);
    }
}
