<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MentorshipRelationship extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'type',
        'source_type',
        'source_id',
        'mentor_id',
        'match_score',
        'status',
        'objectives',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'match_score' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForUser($query, User|string $user)
    {
        $userModel = $user instanceof User ? $user : User::find($user);
        $userId = $userModel?->id ?? (string) $user;

        if ($userModel?->isAdmin()) {
            return $query;
        }

        return $query->where(function ($q) use ($userId) {
            $q->where('mentor_id', $userId)
              ->orWhere(function ($sub) use ($userId) {
                  $sub->whereIn('source_type', self::sourceTypes('user'))
                    ->where('source_id', $userId);
              })
              ->orWhere(function ($sub) use ($userId) {
                  $sub->whereIn('source_type', self::sourceTypes('family_group'))
                    ->whereIn('source_id', FamilyGroup::query()
                        ->select('id')
                        ->where('head_user_id', $userId)
                        ->orWhereIn('id', FamilyMember::query()
                            ->select('family_group_id')
                            ->where('user_id', $userId)));
              })
              ->orWhere(function ($sub) use ($userId) {
                  $sub->whereIn('source_type', self::sourceTypes('cohort'))
                    ->whereIn('source_id', CohortMember::query()
                        ->select('cohort_id')
                        ->where('user_id', $userId));
              })
              ->orWhere(function ($sub) use ($userId) {
                  $sub->whereIn('source_type', self::sourceTypes('company'))
                    ->whereIn('source_id', Company::query()
                        ->select('id')
                        ->where('admin_id', $userId)
                        ->orWhereIn('id', Employee::query()
                            ->select('company_id')
                            ->where('user_id', $userId)));
              });
        });
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function involvesUser(User|string $user): bool
    {
        $userModel = $user instanceof User ? $user : User::find($user);

        if (!$userModel) {
            return false;
        }

        if ($userModel->isAdmin() || $this->mentor_id === $userModel->id) {
            return true;
        }

        return self::userCanAccessSource($userModel, $this->source_type, $this->source_id);
    }

    public static function userCanAccessSource(User $user, string $sourceType, string $sourceId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (in_array($sourceType, self::sourceTypes('user'), true)) {
            return $sourceId === $user->id;
        }

        if (in_array($sourceType, self::sourceTypes('family_group'), true)) {
            return FamilyGroup::where('id', $sourceId)->where('head_user_id', $user->id)->exists()
                || FamilyMember::where('family_group_id', $sourceId)->where('user_id', $user->id)->exists();
        }

        if (in_array($sourceType, self::sourceTypes('cohort'), true)) {
            return CohortMember::where('cohort_id', $sourceId)->where('user_id', $user->id)->exists();
        }

        if (in_array($sourceType, self::sourceTypes('company'), true)) {
            return $user->canAccessCompany($sourceId);
        }

        return false;
    }

    protected static function sourceTypes(string $alias): array
    {
        return match ($alias) {
            'user' => ['user', User::class],
            'family_group' => ['family_group', FamilyGroup::class],
            'cohort' => ['cohort', Cohort::class],
            'company' => ['company', Company::class],
            default => [$alias],
        };
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'relationship_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'relationship_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'relationship_id');
    }
}
