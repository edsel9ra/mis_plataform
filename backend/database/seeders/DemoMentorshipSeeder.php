<?php

namespace Database\Seeders;

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Company;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\LearningPath;
use App\Models\MentorshipRelationship;
use App\Models\PersonalityAssessment;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\SkillTag;
use App\Models\User;
use App\Models\UserLearningProgress;
use App\Models\UserSkill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoMentorshipSeeder extends Seeder
{
    public function run(): void
    {
        $mentors = collect($this->mentorProfiles())->map(fn (array $profile) => $this->upsertUser($profile + ['role' => 'mentor']));

        $this->seedMentorSkills($mentors);

        $mentees = $this->testedUsers();

        if ($mentees->count() < 4) {
            foreach ($this->menteeProfiles() as $profile) {
                $mentees->push($this->upsertUser($profile + ['role' => 'mentee']));
            }
        }

        $mentees = $mentees->unique('id')->take(10)->values();

        foreach ($mentees as $index => $user) {
            $clientType = $user->client_type ?: 'personal';
            $mentor = $mentors->firstWhere('client_type', $clientType) ?? $mentors->first();

            if (!$mentor || $mentor->id === $user->id) {
                continue;
            }

            [$sourceType, $sourceId] = $this->sourceForUser($user, $clientType);
            $matchScore = $this->compatibility($user, $mentor);

            $relationship = MentorshipRelationship::updateOrCreate(
                [
                    'type' => $clientType,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'mentor_id' => $mentor->id,
                ],
                [
                    'match_score' => $matchScore,
                    'status' => 'active',
                    'objectives' => $this->objectiveFor($clientType),
                    'started_at' => now()->subDays(14 + $index),
                ],
            );

            $this->seedSessions($relationship, $user, $mentor, $index);
            $this->seedLearningProgress($user);
        }
    }

    private function upsertUser(array $profile): User
    {
        $user = User::withTrashed()->firstOrNew(['email' => $profile['email']]);

        if ($user->trashed()) {
            $user->restore();
        }

        $user->forceFill([
            'name' => $profile['name'],
            'last_name' => $profile['last_name'],
            'password' => Hash::make('Password123!'),
            'sex' => $profile['sex'] ?? 'N',
            'birth_date' => $profile['birth_date'] ?? null,
            'client_type' => $profile['client_type'],
            'role' => $profile['role'],
            'locale' => 'es',
            'timezone' => 'America/Bogota',
            'is_active' => true,
        ])->save();

        $user->syncApplicationRole($profile['role']);

        PersonalityAssessment::updateOrCreate(
            ['user_id' => $user->id],
            [
                'test_version' => 'ipip-neo-120',
                'answers' => ['seeded' => true],
                'results' => ['factors' => $profile['ocean']],
                'raw_scores' => $profile['ocean'],
                'completed_at' => now()->subDays($profile['assessment_days_ago'] ?? 7),
            ],
        );

        return $user->refresh();
    }

    private function testedUsers()
    {
        return User::query()
            ->whereNotIn('role', ['super_admin', 'admin', 'mentor'])
            ->whereHas('personalityAssessment', fn ($query) => $query->whereNotNull('completed_at'))
            ->with('personalityAssessment')
            ->get();
    }

    private function seedMentorSkills($mentors): void
    {
        $skillMap = [
            'mentor.bienestar@mis.com' => ['Inteligencia Emocional', 'Gestión del Estrés', 'Mindfulness'],
            'mentor.familia@mis.com' => ['Comunicación Familiar', 'Resolución de Conflictos', 'Empatía'],
            'mentor.liderazgo@mis.com' => ['Liderazgo', 'Planeación Estratégica', 'Toma de Decisiones'],
            'mentor.empresa@mis.com' => ['Cultura Organizacional', 'Gestión del Talento', 'Innovación'],
        ];

        foreach ($mentors as $mentor) {
            foreach ($skillMap[$mentor->email] ?? [] as $skillName) {
                $skill = SkillTag::firstOrCreate(['name' => $skillName], ['category' => 'demo']);
                UserSkill::updateOrCreate(
                    ['user_id' => $mentor->id, 'skill_tag_id' => $skill->id],
                    ['level' => 'expert'],
                );
            }
        }
    }

    private function sourceForUser(User $user, string $clientType): array
    {
        return match ($clientType) {
            'familiar' => $this->familySource($user),
            'grupal' => $this->cohortSource($user),
            'empresa' => $this->companySource($user),
            default => ['user', $user->id],
        };
    }

    private function familySource(User $user): array
    {
        $group = FamilyGroup::firstOrCreate(
            ['head_user_id' => $user->id, 'family_name' => 'Familia ' . ($user->last_name ?: $user->name)],
            ['description' => 'Grupo familiar demo para mentorias sistemicas.'],
        );

        FamilyMember::updateOrCreate(
            ['family_group_id' => $group->id, 'user_id' => $user->id],
            ['full_name' => $user->full_name, 'relationship' => 'head'],
        );

        return ['family_group', $group->id];
    }

    private function cohortSource(User $user): array
    {
        $cohort = Cohort::firstOrCreate(
            ['name' => 'Cohorte liderazgo ' . str($user->id)->substr(0, 8)],
            ['created_by' => $user->id, 'description' => 'Cohorte demo con foco en liderazgo y toma de decisiones.', 'status' => 'active'],
        );

        CohortMember::updateOrCreate(
            ['cohort_id' => $cohort->id, 'user_id' => $user->id],
            ['status' => 'active', 'personality_assessment_id' => $user->personalityAssessment?->id],
        );

        return ['cohort', $cohort->id];
    }

    private function companySource(User $user): array
    {
        $company = Company::firstOrCreate(
            ['legal_name' => 'Empresa demo ' . $user->name, 'admin_id' => $user->id],
            ['industry' => 'Servicios profesionales', 'size' => 'sme', 'subscription_status' => 'trial'],
        );

        return ['company', $company->id];
    }

    private function seedSessions(MentorshipRelationship $relationship, User $user, User $mentor, int $index): void
    {
        $sessionType = match ($relationship->type) {
            'familiar' => 'family',
            'grupal' => 'group',
            'empresa' => 'corporate',
            default => 'individual',
        };

        $sessions = [
            [
                'title' => 'Diagnostico inicial y objetivos',
                'description' => 'Revision del perfil OCEAN y definicion de prioridades de mentoría.',
                'scheduled_at' => now()->subDays(6 + $index),
                'status' => 'completed',
                'duration_minutes' => 60,
            ],
            [
                'title' => 'Plan de accion neuroconductual',
                'description' => 'Sesion de seguimiento con acciones sugeridas por perfil y contexto.',
                'scheduled_at' => now()->addDays(3 + $index),
                'status' => 'scheduled',
                'duration_minutes' => $sessionType === 'individual' ? 60 : 90,
            ],
        ];

        foreach ($sessions as $sessionData) {
            $session = Session::updateOrCreate(
                ['relationship_id' => $relationship->id, 'title' => $sessionData['title']],
                $sessionData + ['session_type' => $sessionType],
            );

            foreach ([$mentor->id, $user->id] as $attendeeId) {
                SessionAttendee::updateOrCreate(
                    ['session_id' => $session->id, 'user_id' => $attendeeId],
                    ['attended' => $session->status === 'completed'],
                );
            }
        }
    }

    private function seedLearningProgress(User $user): void
    {
        $paths = LearningPath::query()
            ->where('client_type', $user->client_type ?: 'personal')
            ->with('modules')
            ->take(2)
            ->get();

        foreach ($paths as $index => $path) {
            UserLearningProgress::updateOrCreate(
                ['user_id' => $user->id, 'learning_path_id' => $path->id],
                [
                    'current_module_id' => $path->modules->sortBy('order')->first()?->id,
                    'progress' => $index === 0 ? 35 : 12,
                    'status' => 'in_progress',
                    'started_at' => now()->subDays(5 + $index),
                ],
            );
        }
    }

    private function compatibility(User $user, User $mentor): float
    {
        $userOcean = $user->personalityAssessment?->getOceanScores() ?? [];
        $mentorOcean = $mentor->personalityAssessment?->getOceanScores() ?? [];

        if (!$userOcean || !$mentorOcean) {
            return 78.0;
        }

        $averageDiff = collect(['O', 'C', 'E', 'A', 'N'])
            ->map(fn (string $key) => abs(($userOcean[$key] ?? 50) - ($mentorOcean[$key] ?? 50)))
            ->avg();

        return round(max(65, min(96, 100 - $averageDiff)), 2);
    }

    private function objectiveFor(string $clientType): string
    {
        return match ($clientType) {
            'familiar' => 'Fortalecer comunicacion, cohesion familiar y acuerdos sostenibles.',
            'grupal' => 'Desarrollar liderazgo, colaboracion y toma de decisiones en cohorte.',
            'empresa' => 'Mejorar clima organizacional, bienestar y cultura de alto rendimiento.',
            default => 'Alinear autoconocimiento, bienestar y plan de crecimiento personal.',
        };
    }

    private function mentorProfiles(): array
    {
        return [
            ['name' => 'Valeria', 'last_name' => 'Mendez', 'email' => 'mentor.bienestar@mis.com', 'client_type' => 'personal', 'sex' => 'F', 'birth_date' => '1984-05-14', 'ocean' => ['O' => 74, 'C' => 82, 'E' => 58, 'A' => 88, 'N' => 24]],
            ['name' => 'Lucia', 'last_name' => 'Ramirez', 'email' => 'mentor.familia@mis.com', 'client_type' => 'familiar', 'sex' => 'F', 'birth_date' => '1979-11-02', 'ocean' => ['O' => 68, 'C' => 76, 'E' => 62, 'A' => 92, 'N' => 28]],
            ['name' => 'Miguel', 'last_name' => 'Torres', 'email' => 'mentor.liderazgo@mis.com', 'client_type' => 'grupal', 'sex' => 'M', 'birth_date' => '1981-03-22', 'ocean' => ['O' => 81, 'C' => 86, 'E' => 78, 'A' => 70, 'N' => 22]],
            ['name' => 'Carmen', 'last_name' => 'Vega', 'email' => 'mentor.empresa@mis.com', 'client_type' => 'empresa', 'sex' => 'F', 'birth_date' => '1977-09-08', 'ocean' => ['O' => 77, 'C' => 90, 'E' => 74, 'A' => 76, 'N' => 20]],
        ];
    }

    private function menteeProfiles(): array
    {
        return [
            ['name' => 'Ana', 'last_name' => 'Lopez', 'email' => 'ana.demo@mis.com', 'client_type' => 'personal', 'sex' => 'F', 'birth_date' => '1992-04-11', 'ocean' => ['O' => 69, 'C' => 61, 'E' => 48, 'A' => 73, 'N' => 54], 'assessment_days_ago' => 10],
            ['name' => 'Carlos', 'last_name' => 'Pineda', 'email' => 'carlos.demo@mis.com', 'client_type' => 'familiar', 'sex' => 'M', 'birth_date' => '1988-01-20', 'ocean' => ['O' => 58, 'C' => 66, 'E' => 52, 'A' => 81, 'N' => 45], 'assessment_days_ago' => 9],
            ['name' => 'Diana', 'last_name' => 'Suarez', 'email' => 'diana.demo@mis.com', 'client_type' => 'grupal', 'sex' => 'F', 'birth_date' => '1990-07-17', 'ocean' => ['O' => 82, 'C' => 74, 'E' => 68, 'A' => 67, 'N' => 36], 'assessment_days_ago' => 8],
            ['name' => 'Felipe', 'last_name' => 'Acosta', 'email' => 'felipe.demo@mis.com', 'client_type' => 'empresa', 'sex' => 'M', 'birth_date' => '1985-12-03', 'ocean' => ['O' => 64, 'C' => 79, 'E' => 71, 'A' => 62, 'N' => 42], 'assessment_days_ago' => 7],
        ];
    }
}
