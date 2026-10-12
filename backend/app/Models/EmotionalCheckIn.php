<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmotionalCheckIn extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'relationship_id',
        'mentee_id',
        'mood_score',
        'stress_score',
        'note',
        'checked_in_at',
    ];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime'];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(MentorshipRelationship::class);
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }
}
