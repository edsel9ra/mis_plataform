<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyMember extends Model
{
    use HasUuids;

    protected $fillable = [
        'family_group_id',
        'user_id',
        'full_name',
        'age',
        'relationship',
        'personality_assessment_id',
    ];

    public function familyGroup(): BelongsTo
    {
        return $this->belongsTo(FamilyGroup::class);
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
