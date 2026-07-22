<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_id',
        'user_id',
        'position',
        'department',
        'manager_name',
        'status',
        'invited_at',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
