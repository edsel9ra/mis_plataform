<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Separate correspondence between a minimal deletion event and the real
 * actor (docs/politica-retencion.md §4.5). It is reachable only from this
 * side — the event itself never resolves its actor.
 */
class EvidenceDeletionActorLink extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'actor_id',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EvidenceDeletionEvent::class, 'event_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
