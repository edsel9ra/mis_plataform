<?php

namespace App\Models;

use App\Enums\ObjectiveScope;
use App\Enums\ObjectiveStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Objective extends Model
{
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'scope',
        'owner_type',
        'owner_id',
        'relationship_id',
        'created_by',
        'title',
        'description',
        'success_criteria',
        'status',
        'due_at',
        'achieved_at',
        'proposed_by',
    ];

    protected function casts(): array
    {
        return [
            'scope' => ObjectiveScope::class,
            'status' => ObjectiveStatus::class,
            'due_at' => 'datetime',
            'achieved_at' => 'datetime',
        ];
    }

    public function setOwnerTypeAttribute(?string $value): void
    {
        $this->attributes['owner_type'] = self::canonicalOwnerType($value);
    }

    public function getOwnerTypeAttribute(?string $value): ?string
    {
        return self::canonicalOwnerType($value);
    }

    public static function canonicalOwnerType(?string $value): ?string
    {
        return match ($value) {
            User::class, 'user' => 'user',
            FamilyGroup::class, 'family_group' => 'family_group',
            Cohort::class, 'cohort' => 'cohort',
            Company::class, 'company' => 'company',
            default => $value,
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('objective')
            ->logOnly(['status', 'due_at', 'success_criteria', 'owner_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function scopeForOwner(Builder $query, string $ownerType, string $ownerId): Builder
    {
        return $query
            ->where('owner_type', self::canonicalOwnerType($ownerType))
            ->where('owner_id', $ownerId);
    }

    public function scopeIndividual(Builder $query): Builder
    {
        return $query->where('scope', ObjectiveScope::Individual->value);
    }

    public function scopeCollective(Builder $query): Builder
    {
        return $query->where('scope', ObjectiveScope::Collective->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ObjectiveStatus::NotStarted->value,
            ObjectiveStatus::InProgress->value,
        ]);
    }

    public function scopeOverdue(Builder $query, mixed $now = null): Builder
    {
        $now ??= now();

        return $query
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->where('status', '!=', ObjectiveStatus::Achieved->value);
    }

    public function isOverdue(mixed $now = null): bool
    {
        $now ??= now();

        if ($this->due_at === null) {
            return false;
        }

        if ($this->status === ObjectiveStatus::Achieved) {
            return false;
        }

        return $this->due_at->lt($now);
    }

    public function canTransitionTo(ObjectiveStatus|string $status): bool
    {
        return $this->status instanceof ObjectiveStatus
            && $this->status->canTransitionTo($status);
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(MentorshipRelationship::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(ObjectiveEvidence::class);
    }
}
