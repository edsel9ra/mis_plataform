<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'client_type',
        'code',
        'name',
        'description',
        'features',
        'entitlements',
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
            'entitlements' => 'array',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasEntitlement(string $key): bool
    {
        $entitlements = $this->entitlements;

        if (!is_array($entitlements) || !array_key_exists($key, $entitlements)) {
            return false;
        }

        return filter_var($entitlements[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? (is_numeric($entitlements[$key]) && (float) $entitlements[$key] > 0);
    }

    public function getNameAttribute($value)
    {
        $locale = app()->getLocale();
        $data = json_decode($value, true) ?? [];
        return $data[$locale] ?? $data['es'] ?? $value;
    }
}
