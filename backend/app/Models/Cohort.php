<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cohort extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'max_members',
        'plan_id',
        'status',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(CohortMember::class);
    }

    public function memberUsers()
    {
        return $this->hasManyThrough(User::class, CohortMember::class, 'cohort_id', 'id', 'id', 'user_id');
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
