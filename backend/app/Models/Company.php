<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'legal_name',
        'tax_id',
        'industry',
        'size',
        'admin_id',
        'plan_id',
        'subscription_status',
        'logo',
        'website',
        'phone',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function employeeUsers()
    {
        return $this->hasManyThrough(User::class, Employee::class, 'company_id', 'id', 'id', 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function relationships(): MorphMany
    {
        return $this->morphMany(MentorshipRelationship::class, 'source');
    }

    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscriber');
    }
}
