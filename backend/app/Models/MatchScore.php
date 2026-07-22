<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchScore extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'mentor_id',
        'context_type',
        'context_id',
        'score',
        'breakdown',
        'personality_compatibility',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'breakdown' => 'array',
            'personality_compatibility' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }
}
