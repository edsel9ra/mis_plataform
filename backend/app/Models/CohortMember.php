<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CohortMember extends Model
{
    use HasUuids;

    protected $fillable = [
        'cohort_id',
        'user_id',
        'personality_assessment_id',
        'status',
    ];

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function personalityAssessment(): BelongsTo
    {
        return $this->belongsTo(PersonalityAssessment::class);
    }
}
