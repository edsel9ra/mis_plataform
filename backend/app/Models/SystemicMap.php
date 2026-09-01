<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemicMap extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'relationship_id',
        'mentor_id',
        'mentee_id',
        'title',
        'notes',
        'is_private',
    ];

    protected function casts(): array
    {
        return ['is_private' => 'boolean'];
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

    public function nodes(): HasMany
    {
        return $this->hasMany(SystemicMapNode::class);
    }

    public function edges(): HasMany
    {
        return $this->hasMany(SystemicMapEdge::class);
    }
}
