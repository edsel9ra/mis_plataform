<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PathModule extends Model
{
    use HasUuids;

    protected $fillable = [
        'learning_path_id',
        'title',
        'description',
        'order',
        'estimated_minutes',
    ];

    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(LearningResource::class, 'module_id');
    }
}
