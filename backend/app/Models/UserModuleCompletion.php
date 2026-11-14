<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserModuleCompletion extends Model
{
    use HasUuids;

    protected $table = 'user_module_completions';

    protected $fillable = [
        'user_id',
        'path_module_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pathModule(): BelongsTo
    {
        return $this->belongsTo(PathModule::class);
    }
}
