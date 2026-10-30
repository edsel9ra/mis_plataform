<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SessionScheduleChange extends Model
{
    use HasUuids;

    protected $fillable = [
        'session_id',
        'scheduled_at_before',
        'scheduled_at_after',
        'timezone_before',
        'timezone_after',
        'duration_minutes_before',
        'duration_minutes_after',
        'actor_id',
        'occurred_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at_before' => 'datetime',
            'scheduled_at_after' => 'datetime',
            'duration_minutes_before' => 'integer',
            'duration_minutes_after' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Session schedule changes are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Session schedule changes are append-only.');
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
