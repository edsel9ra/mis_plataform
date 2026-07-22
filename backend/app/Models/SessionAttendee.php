<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAttendee extends Model
{
    use HasUuids;

    protected $fillable = [
        'session_id',
        'user_id',
        'attended',
    ];

    protected function casts(): array
    {
        return [
            'attended' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
