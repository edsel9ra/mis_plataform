<?php

namespace App\Models;

use App\Enums\AvailabilityMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorAvailabilityException extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'user_id',
        'mode',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'timezone',
        'reason',
    ];

    protected $hidden = [
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'mode' => AvailabilityMode::class,
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'start_time' => 'datetime:H:i:s',
            'end_time' => 'datetime:H:i:s',
        ];
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array{mode: AvailabilityMode, start_date: string, end_date: ?string, start_time: ?string, end_time: ?string, timezone: string}
     */
    public function toRuleArray(): array
    {
        return [
            'mode' => $this->mode instanceof AvailabilityMode
                ? $this->mode
                : AvailabilityMode::from((string) $this->mode),
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'start_time' => $this->start_time?->format('H:i:s'),
            'end_time' => $this->end_time?->format('H:i:s'),
            'timezone' => $this->timezone,
        ];
    }
}
