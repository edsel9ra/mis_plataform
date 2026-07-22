<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningPath extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'personality_tags',
        'client_type',
        'level',
        'estimated_hours',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'personality_tags' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function modules(): HasMany
    {
        return $this->hasMany(PathModule::class);
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(UserLearningProgress::class);
    }
}
