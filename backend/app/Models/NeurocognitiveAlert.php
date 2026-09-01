<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NeurocognitiveAlert extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'relationship_id',
        'mentor_id',
        'mentee_id',
        'type',
        'severity',
        'dedupe_key',
        'data',
        'detected_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(MentorshipRelationship::class);
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }
}
