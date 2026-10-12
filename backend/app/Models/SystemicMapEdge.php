<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemicMapEdge extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'systemic_map_id',
        'from_node_id',
        'to_node_id',
        'label',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function map(): BelongsTo
    {
        return $this->belongsTo(SystemicMap::class, 'systemic_map_id');
    }

    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(SystemicMapNode::class, 'from_node_id');
    }

    public function toNode(): BelongsTo
    {
        return $this->belongsTo(SystemicMapNode::class, 'to_node_id');
    }
}
