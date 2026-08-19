<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    use HasUuids;

    protected $fillable = [
        'module_id',
        'slug',
        'title',
        'short_name',
        'description',
        'category',
        'client_types',
        'questions',
        'passing_score',
        'time_limit_minutes',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'short_name' => 'array',
            'description' => 'array',
            'client_types' => 'array',
            'questions' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(PathModule::class, 'module_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(AssessmentResult::class);
    }
}
