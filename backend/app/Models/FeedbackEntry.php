<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class FeedbackEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'feedback_thread_id',
        'author_id',
        'body',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(FeedbackThread::class, 'feedback_thread_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function mediaAssets(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'mediaable');
    }
}
