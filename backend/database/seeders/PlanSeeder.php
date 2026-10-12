<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'personal_basic',
                'client_type' => 'personal',
                'name' => ['es' => 'Personal Básico', 'en' => 'Basic Individual', 'pt' => 'Pessoal Básico'],
                'description' => ['es' => '4 sesiones al mes con mentor personalizado', 'en' => '4 monthly sessions with personalized mentor', 'pt' => '4 sessões mensais com mentor personalizado'],
                'features' => [
                    $this->feature('4 sesiones/mes', '4 sessions/month', '4 sessões/mês'),
                    $this->feature('Chat ilimitado', 'Unlimited chat', 'Chat ilimitado'),
                    $this->feature('Perfil OCEAN', 'OCEAN profile', 'Perfil OCEAN'),
                    $this->feature('Ruta de aprendizaje', 'Learning path', 'Trilha de aprendizagem'),
                ],
                'entitlements' => $this->personalEntitlements(3),
                'price_monthly' => 499.00,
                'price_yearly' => 4999.00,
                'max_sessions_per_month' => 4,
                'max_members' => 1,
                'max_mentors' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'personal_premium',
                'client_type' => 'personal',
                'name' => ['es' => 'Personal Premium', 'en' => 'Premium Individual', 'pt' => 'Pessoal Premium'],
                'description' => ['es' => '8 sesiones al mes, recursos avanzados', 'en' => '8 monthly sessions with advanced resources', 'pt' => '8 sessões mensais com recursos avançados'],
                'features' => [
                    $this->feature('8 sesiones/mes', '8 sessions/month', '8 sessões/mês'),
                    $this->feature('Chat ilimitado', 'Unlimited chat', 'Chat ilimitado'),
                    $this->feature('Recursos avanzados', 'Advanced resources', 'Recursos avançados'),
                    $this->feature('Evaluaciones', 'Assessments', 'Avaliações'),
                    $this->feature('Certificado blockchain', 'Blockchain certificate', 'Certificado blockchain'),
                ],
                'entitlements' => $this->personalEntitlements(10),
                'price_monthly' => 899.00,
                'price_yearly' => 8999.00,
                'max_sessions_per_month' => 8,
                'max_members' => 1,
                'max_mentors' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'family',
                'client_type' => 'familiar',
                'name' => ['es' => 'Familiar', 'en' => 'Family', 'pt' => 'Familiar'],
                'description' => ['es' => 'Hasta 6 miembros, 2 sesiones grupales al mes', 'en' => 'Up to 6 members, 2 group sessions per month', 'pt' => 'Até 6 membros, 2 sessões em grupo por mês'],
                'features' => [
                    $this->feature('Hasta 6 miembros', 'Up to 6 members', 'Até 6 membros'),
                    $this->feature('2 sesiones grupales/mes', '2 group sessions/month', '2 sessões em grupo/mês'),
                    $this->feature('Perfiles OCEAN por miembro', 'OCEAN profiles per member', 'Perfis OCEAN por membro'),
                    $this->feature('Chat grupal', 'Group chat', 'Chat em grupo'),
                ],
                'entitlements' => [],
                'price_monthly' => 1299.00,
                'price_yearly' => 12999.00,
                'max_sessions_per_month' => 6,
                'max_members' => 6,
                'max_mentors' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'group',
                'client_type' => 'grupal',
                'name' => ['es' => 'Grupal', 'en' => 'Group', 'pt' => 'Grupal'],
                'description' => ['es' => 'Cohortes de 5-20 personas con mentor dedicado', 'en' => '5-20 person cohorts with dedicated mentor', 'pt' => 'Coortes de 5-20 pessoas com mentor dedicado'],
                'features' => [
                    $this->feature('5-20 miembros', '5-20 members', '5-20 membros'),
                    $this->feature('4 sesiones grupales/mes', '4 group sessions/month', '4 sessões em grupo/mês'),
                    $this->feature('Foro del cohorte', 'Cohort forum', 'Fórum da coorte'),
                    $this->feature('Progreso grupal', 'Group progress', 'Progresso do grupo'),
                ],
                'entitlements' => [],
                'price_monthly' => 2499.00,
                'price_yearly' => 24999.00,
                'max_sessions_per_month' => 4,
                'max_members' => 20,
                'max_mentors' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'startup',
                'client_type' => 'empresa',
                'name' => ['es' => 'Startup', 'en' => 'Startup', 'pt' => 'Startup'],
                'description' => ['es' => 'Hasta 20 empleados, dashboard corporativo', 'en' => 'Up to 20 employees, corporate dashboard', 'pt' => 'Até 20 funcionários, painel corporativo'],
                'features' => [
                    $this->feature('Hasta 20 empleados', 'Up to 20 employees', 'Até 20 funcionários'),
                    $this->feature('Dashboard corporativo', 'Corporate dashboard', 'Painel corporativo'),
                    $this->feature('Reportes', 'Reports', 'Relatórios'),
                    $this->feature('Matching personalizado', 'Personalized matching', 'Matching personalizado'),
                ],
                'entitlements' => [],
                'price_monthly' => 4999.00,
                'price_yearly' => 49999.00,
                'max_sessions_per_month' => 40,
                'max_members' => 20,
                'max_mentors' => 5,
                'is_active' => true,
            ],
            [
                'code' => 'corporate',
                'client_type' => 'empresa',
                'name' => ['es' => 'Corporate', 'en' => 'Corporate', 'pt' => 'Corporate'],
                'description' => ['es' => 'Hasta 100 empleados, todo incluido', 'en' => 'Up to 100 employees, all included', 'pt' => 'Até 100 funcionários, tudo incluído'],
                'features' => [
                    $this->feature('Hasta 100 empleados', 'Up to 100 employees', 'Até 100 funcionários'),
                    $this->feature('Dashboard avanzado', 'Advanced dashboard', 'Painel avançado'),
                    $this->feature('API', 'API', 'API'),
                    $this->feature('Soporte prioritario', 'Priority support', 'Suporte prioritário'),
                    $this->feature('Certificados blockchain', 'Blockchain certificates', 'Certificados blockchain'),
                ],
                'entitlements' => [],
                'price_monthly' => 14999.00,
                'price_yearly' => 149999.00,
                'max_sessions_per_month' => 200,
                'max_members' => 100,
                'max_mentors' => 20,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            $existingPlan = Plan::query()
                ->where('client_type', $plan['client_type'])
                ->get()
                ->first(fn (Plan $existingPlan) => $this->spanishName($existingPlan) === $plan['name']['es']);

            ($existingPlan ?? new Plan())->fill($plan)->save();
        }
    }

    private function spanishName(Plan $plan): ?string
    {
        $name = json_decode((string) $plan->getRawOriginal('name'), true);

        if (is_string($name)) {
            $name = json_decode($name, true);
        }

        return is_array($name) ? ($name['es'] ?? null) : null;
    }

    private function feature(string $spanish, string $english, string $portuguese): array
    {
        return ['es' => $spanish, 'en' => $english, 'pt' => $portuguese];
    }

    private function personalEntitlements(int $maxActiveChallenges): array
    {
        return [
            'mentor_workspace' => true,
            'attention_tracking' => true,
            'emotional_check_ins' => true,
            'challenges' => true,
            'challenge_media' => true,
            'challenge_feedback' => true,
            'mentor_observations' => true,
            'systemic_maps' => true,
            'ocean' => true,
            'neurocognitive' => true,
            'systemic_map' => true,
            'feedback' => true,
            'max_active_challenges' => $maxActiveChallenges,
        ];
    }
}
