<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Session extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'relationship_id',
        'session_type',
        'title',
        'description',
        'scheduled_at',
        'duration_minutes',
        'meet_link',
        'meet_event_id',
        'status',
        'mentor_notes',
        'mentee_notes',
        'recording_url',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>=', now())
            ->whereIn('status', ['scheduled', 'in_progress']);
    }

    public function scopePast($query)
    {
        return $query->where('scheduled_at', '<', now())
            ->whereIn('status', ['completed', 'canceled']);
    }

    public function scopeForUser($query, User|string $user)
    {
        return $query->whereHas('relationship', function ($q) use ($user) {
            $q->forUser($user);
        });
    }

    public function involvesUser(User|string $user): bool
    {
        return $this->relationship?->involvesUser($user)
            ?? $this->relationship()->first()?->involvesUser($user)
            ?? false;
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(MentorshipRelationship::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(SessionAttendee::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
