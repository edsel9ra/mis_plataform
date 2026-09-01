<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Challenge extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const VALID_STATUSES = [
        'template',
        'assigned',
        'in_progress',
        'completed',
        'canceled',
    ];

    private const VALID_TRANSITIONS = [
        'template' => ['template'],
        'assigned' => ['assigned', 'in_progress', 'canceled', 'completed'],
        'in_progress' => ['in_progress', 'completed', 'canceled'],
        'completed' => ['completed'],
        'canceled' => ['canceled'],
    ];

    protected $fillable = [
        'template_id',
        'relationship_id',
        'mentor_id',
        'mentee_id',
        'title',
        'description',
        'instructions',
        'objective',
        'success_criteria',
        'presentation_format',
        'frequency',
        'progress',
        'status',
        'due_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'progress' => 'integer',
        ];
    }

    public function isTemplate(): bool
    {
        return $this->status === 'template' && $this->relationship_id === null && $this->mentee_id === null;
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::VALID_TRANSITIONS[$this->status] ?? [], true);
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(MentorshipRelationship::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(self::class, 'template_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(self::class, 'template_id');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(ChallengeCheckIn::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ChallengeSubmission::class);
    }

    public function feedbackThreads(): HasMany
    {
        return $this->hasMany(FeedbackThread::class);
    }

    public function mediaAssets(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'mediaable');
    }
}
