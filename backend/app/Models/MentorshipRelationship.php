<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MentorshipRelationship extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'type',
        'source_type',
        'source_id',
        'mentor_id',
        'match_score',
        'status',
        'objectives',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'match_score' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('mentor_id', $userId)
              ->orWhere(function ($sub) use ($userId) {
                  $sub->where('source_type', 'user')
                    ->where('source_id', $userId);
              });
        });
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
