<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionAttendee extends Model
{
    use HasUuids;
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'attended',
        'left_at',
        'attendance_status',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'attended' => 'boolean',
            'left_at' => 'datetime',
            'attendance_status' => AttendanceStatus::class,
            'confirmed_at' => 'datetime',
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

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(SessionAttendanceEvent::class);
    }
}
