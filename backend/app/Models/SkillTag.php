<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillTag extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'category',
    ];

    public function userSkills(): HasMany
    {
        return $this->hasMany(UserSkill::class);
    }
}
