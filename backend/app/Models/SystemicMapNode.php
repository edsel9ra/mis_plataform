<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemicMapNode extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'systemic_map_id',
        'node_key',
        'label',
        'kind',
        'position',
        'notes',
    ];

    protected function casts(): array
    {
        return ['position' => 'array'];
    }

    public function map(): BelongsTo
    {
        return $this->belongsTo(SystemicMap::class, 'systemic_map_id');
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(SystemicMapEdge::class, 'from_node_id');
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(SystemicMapEdge::class, 'to_node_id');
    }
}
