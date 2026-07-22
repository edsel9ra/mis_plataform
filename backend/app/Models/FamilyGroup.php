<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyGroup extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'head_user_id',
        'family_name',
        'description',
        'plan_id',
    ];

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
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
