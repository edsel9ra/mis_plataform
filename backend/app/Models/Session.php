<?php

namespace App\Models;

use App\Services\Scheduling\Rules\LifecycleRules;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Session extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'relationship_id',
        'session_type',
        'title',
        'description',
        'scheduled_at',
        'end_at',
        'completed_at',
        'completed_by',
        'canceled_at',
        'canceled_by',
        'cancellation_reason',
        'canceled_late',
        'cancellation_lead_minutes',
        'timezone',
        'duration_minutes',
        'meet_link',
        'meet_event_id',
        'meet_synced_schedule_hash',
        'status',
        'mentor_notes',
        'mentee_notes',
        'recording_url',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'end_at' => 'datetime',
            'completed_at' => 'datetime',
            'canceled_at' => 'datetime',
            'canceled_late' => 'boolean',
            'cancellation_lead_minutes' => 'integer',
            'meet_attempts' => 'integer',
            'meet_last_attempt_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Session $session): void {
            $scheduledAt = $session->getAttributes()['scheduled_at'] ?? null;

            if ($scheduledAt === null || $session->duration_minutes === null) {
                return;
            }

            $endAt = LifecycleRules::sessionEnd(
                CarbonImmutable::parse($scheduledAt, 'UTC'),
                (int) $session->duration_minutes,
            );

            $session->end_at = $endAt->format($session->getDateFormat());
        });
    }

    protected function timezone(): Attribute
    {
        return Attribute::get(
            fn (?string $value): string => $value ?? config('scheduling.default_timezone'),
        );
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

    public function attendanceEvents(): HasManyThrough
    {
        return $this->hasManyThrough(
            SessionAttendanceEvent::class,
            SessionAttendee::class,
            'session_id',
            'session_attendee_id',
        );
    }

    public function participantExclusions(): HasMany
    {
        return $this->hasMany(SessionParticipantExclusion::class);
    }

    public function scheduleChanges(): HasMany
    {
        return $this->hasMany(SessionScheduleChange::class);
    }

    public function end(): ?Carbon
    {
        return $this->end_at;
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
