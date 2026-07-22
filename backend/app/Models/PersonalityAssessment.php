<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalityAssessment extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'test_version',
        'answers',
        'results',
        'raw_scores',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'results' => 'array',
            'raw_scores' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getOceanScores(): array
    {
        return $this->results['factors'] ?? [];
    }

    public function getOpenness(): ?float
    {
        return $this->results['factors']['O'] ?? null;
    }

    public function getConscientiousness(): ?float
    {
        return $this->results['factors']['C'] ?? null;
    }

    public function getExtraversion(): ?float
    {
        return $this->results['factors']['E'] ?? null;
    }

    public function getAgreeableness(): ?float
    {
        return $this->results['factors']['A'] ?? null;
    }

    public function getNeuroticism(): ?float
    {
        return $this->results['factors']['N'] ?? null;
    }
}
