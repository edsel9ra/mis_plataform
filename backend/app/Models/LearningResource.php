<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningResource extends Model
{
    use HasUuids;

    protected $fillable = [
        'module_id',
        'type',
        'title',
        'description',
        'url',
        'order',
        'is_free',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'is_free' => 'boolean',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(PathModule::class, 'module_id');
    }
}
