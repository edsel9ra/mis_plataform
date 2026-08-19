<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Subscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'subscriber_type',
        'subscriber_id',
        'plan_id',
        'status',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'payment_provider',
        'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeCurrent($query)
    {
        $now = now();

        return $query->whereIn('status', ['trial', 'active'])
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            })
            ->where(function ($query) use ($now) {
                $query->where('status', 'active')
                    ->orWhereNull('trial_ends_at')
                    ->orWhere('trial_ends_at', '>', $now);
            });
    }

    public function isActive(): bool
    {
        if (!in_array($this->status, ['trial', 'active'], true)) {
            return false;
        }

        $now = now();

        return (!$this->ends_at || $this->ends_at->isAfter($now))
            && ($this->status !== 'trial' || !$this->trial_ends_at || $this->trial_ends_at->isAfter($now));
    }
}
