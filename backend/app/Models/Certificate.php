<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'relationship_id',
        'type',
        'title',
        'description',
        'metadata',
        'ipfs_cid',
        'ipfs_uri',
        'blockchain_tx_hash',
        'blockchain_contract_address',
        'blockchain_token_id',
        'issued_at',
        'expires_at',
        'revoked',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(MentorshipRelationship::class);
    }

    public function getVerificationUrl(): string
    {
        return route('certificates.verify', $this->id);
    }
}
