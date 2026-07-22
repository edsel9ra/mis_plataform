<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasUuids;

    protected $fillable = [
        'session_id',
        'rater_id',
        'rating',
        'comment',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }
}
