<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, HasRoles, Notifiable, SoftDeletes;

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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'mentor_id');
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

    public function canAccessCompany(Company|string $company): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $companyId = $company instanceof Company ? $company->id : $company;

        return $this->company_id === $companyId
            || Company::where('id', $companyId)->where('admin_id', $this->id)->exists()
            || Employee::where('company_id', $companyId)->where('user_id', $this->id)->exists();
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

    public function getFullNameAttribute(): string
    {
        return trim($this->name . ' ' . $this->last_name);
    }
}
