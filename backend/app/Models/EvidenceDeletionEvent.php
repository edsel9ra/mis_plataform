<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Minimal deletion event (docs/politica-retencion.md §4.1, plan §9): when an
 * assessment source linked to a competency is deleted, only this record is
 * kept — discriminators, a per-event random actor pseudonym and dates. The
 * real actor lives ONLY in evidence_deletion_actor_links; this model
 * deliberately exposes no actor/actorLink relation.
 */
class EvidenceDeletionEvent extends Model
{
    use HasFactory, HasUuids;

    public const CHANGE_SOURCE_DELETED = 'source_deleted';

    protected $fillable = [
        'subject_id',
        'source_type',
        'competency_type',
        'competency_key',
        'change_type',
        'actor_pseudonym',
        'occurred_at',
        'expires_at',
    ];

    /**
     * The internal subject reference is never serialized outside the server.
     *
     * @var array<int, string>
     */
    protected $hidden = ['subject_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }
}
