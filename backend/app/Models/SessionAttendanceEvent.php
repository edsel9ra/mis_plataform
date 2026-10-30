<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\SessionAttendanceEventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SessionAttendanceEvent extends Model
{
    use HasUuids;
    use HasFactory;

    protected $fillable = [
        'session_attendee_id',
        'event_type',
        'attendance_status',
        'recorded_by',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => SessionAttendanceEventType::class,
            'attendance_status' => AttendanceStatus::class,
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Attendance events are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Attendance events are append-only.');
        });
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(SessionAttendee::class, 'session_attendee_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
