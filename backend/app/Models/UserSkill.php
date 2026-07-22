<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSkill extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'skill_tag_id',
        'level',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skillTag(): BelongsTo
    {
        return $this->belongsTo(SkillTag::class);
    }
}
