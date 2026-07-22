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

    public function isActive(): bool
    {
        return in_array($this->status, ['trial', 'active']);
    }
}
