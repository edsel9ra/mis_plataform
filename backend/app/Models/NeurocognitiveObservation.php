<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NeurocognitiveObservation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'relationship_id',
        'mentor_id',
        'mentee_id',
        'challenge_id',
        'attention_score',
        'engagement_score',
        'dimensions',
        'note',
        'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'dimensions' => 'array',
            'observed_at' => 'datetime',
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

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }
}
