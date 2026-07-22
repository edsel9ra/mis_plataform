<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'client_type',
        'name',
        'description',
        'features',
        'price_monthly',
        'price_yearly',
        'max_sessions_per_month',
        'max_members',
        'max_mentors',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'features' => 'array',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getNameAttribute($value)
    {
        $locale = app()->getLocale();
        $data = json_decode($value, true) ?? [];
        return $data[$locale] ?? $data['es'] ?? $value;
    }
}
