<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Scout\Searchable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, HasRoles, Notifiable, Searchable, SoftDeletes;

    protected $fillable = [
        'client_type',
        'role',
        'company_id',
        'name',
        'last_name',
        'email',
        'password',
        'sex',
        'birth_date',
        'provider',
        'provider_id',
        'avatar',
        'timezone',
        'locale',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'birth_date' => 'date:Y-m-d',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (User $user) {
            if ($user->wasChanged('is_active') && !$user->is_active) {
                $user->tokens()->delete();
            }
        });

        static::deleting(function (User $user) {
            $user->tokens()->delete();
        });
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value === null
            ? null
            : mb_strtolower(trim($value));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function oauthAccounts(): HasMany
    {
        return $this->hasMany(OAuthAccount::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function personalityAssessment(): HasOne
    {
        return $this->hasOne(PersonalityAssessment::class);
    }

    public function mentorRelationships(): HasMany
    {
        return $this->hasMany(MentorshipRelationship::class, 'mentor_id');
    }

    public function menteeRelationships(): MorphMany
    {
        return $this->morphMany(MentorshipRelationship::class, 'source');
    }

    public function sessions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Session::class,
            MentorshipRelationship::class,
            'mentor_id',
            'relationship_id',
            'id',
            'id',
        );
    }

    public function attendedSessions(): HasMany
    {
        return $this->hasMany(SessionAttendee::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(UserSkill::class);
    }

    public function learningProgress(): HasMany
    {
        return $this->hasMany(UserLearningProgress::class);
    }

    public function assessmentResults(): HasMany
    {
        return $this->hasMany(AssessmentResult::class);
    }

    public function challengesAsMentor(): HasMany
    {
        return $this->hasMany(Challenge::class, 'mentor_id');
    }

    public function challengesAsMentee(): HasMany
    {
        return $this->hasMany(Challenge::class, 'mentee_id');
    }

    public function challengeSubmissions(): HasMany
    {
        return $this->hasMany(ChallengeSubmission::class, 'mentee_id');
    }

    public function emotionalCheckIns(): HasMany
    {
        return $this->hasMany(EmotionalCheckIn::class, 'mentee_id');
    }

    public function observationsAsMentor(): HasMany
    {
        return $this->hasMany(NeurocognitiveObservation::class, 'mentor_id');
    }

    public function observationsAsMentee(): HasMany
    {
        return $this->hasMany(NeurocognitiveObservation::class, 'mentee_id');
    }

    public function alertsAsMentor(): HasMany
    {
        return $this->hasMany(NeurocognitiveAlert::class, 'mentor_id');
    }

    public function systemicMapsAsMentor(): HasMany
    {
        return $this->hasMany(SystemicMap::class, 'mentor_id');
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'uploaded_by');
    }

    public function matchScoresAsMentee(): HasMany
    {
        return $this->hasMany(MatchScore::class, 'user_id');
    }

    public function matchScoresAsMentor(): HasMany
    {
        return $this->hasMany(MatchScore::class, 'mentor_id');
    }

    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscriber');
    }

    public function scopeByClientType($query, string $type)
    {
        return $query->where('client_type', $type);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
              ->orWhere('email', 'ilike', "%{$term}%")
              ->orWhere('last_name', 'ilike', "%{$term}%");
        });
    }

    public function isMentor(): bool
    {
        return $this->role === UserRole::Mentor->value;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->role === UserRole::CompanyAdmin->value;
    }

    public function isEmployee(): bool
    {
        return $this->role === UserRole::Employee->value;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [UserRole::Admin->value, UserRole::SuperAdmin->value]);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin->value;
    }

    public function canAccessCompany(Company|string $company): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $companyId = $company instanceof Company ? $company->id : $company;

        return Company::where('id', $companyId)->where('admin_id', $this->id)->exists()
            || Employee::active()
                ->where('company_id', $companyId)
                ->where('user_id', $this->id)
                ->exists();
    }

    public function canManageCompany(Company|string $company): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $companyId = $company instanceof Company ? $company->id : $company;

        return Company::where('id', $companyId)->where('admin_id', $this->id)->exists();
    }

    public function syncApplicationRole(?string $role = null): void
    {
        $roleName = $role ?? $this->role;

        if (!$roleName) {
            return;
        }

        try {
            if (Role::where('name', $roleName)->exists()) {
                $this->syncRoles([$roleName]);
            }
        } catch (\Throwable) {
            // Role tables may not exist yet during early setup commands.
        }
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'role' => $this->role,
            'client_type' => $this->client_type,
            'is_active' => $this->is_active,
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->name . ' ' . $this->last_name);
    }
}
