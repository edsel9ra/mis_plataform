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
                'client_type' => 'personal',
                'name' => json_encode(['es' => 'Personal Básico', 'en' => 'Basic Individual', 'pt' => 'Pessoal Básico']),
                'description' => json_encode(['es' => '4 sesiones al mes con mentor personalizado', 'en' => '4 monthly sessions with personalized mentor', 'pt' => '4 sessões mensais com mentor personalizado']),
                'features' => json_encode(['4 sesiones/mes', 'Chat ilimitado', 'Perfil OCEAN', 'Ruta de aprendizaje']),
                'price_monthly' => 499.00,
                'price_yearly' => 4999.00,
                'max_sessions_per_month' => 4,
                'max_members' => 1,
                'max_mentors' => 1,
            ],
            [
                'client_type' => 'personal',
                'name' => json_encode(['es' => 'Personal Premium', 'en' => 'Premium Individual', 'pt' => 'Pessoal Premium']),
                'description' => json_encode(['es' => '8 sesiones al mes, recursos avanzados', 'en' => '8 monthly sessions with advanced resources', 'pt' => '8 sessões mensais com recursos avançados']),
                'features' => json_encode(['8 sesiones/mes', 'Chat ilimitado', 'Recursos avanzados', 'Evaluaciones', 'Certificado blockchain']),
                'price_monthly' => 899.00,
                'price_yearly' => 8999.00,
                'max_sessions_per_month' => 8,
                'max_members' => 1,
                'max_mentors' => 1,
            ],
            [
                'client_type' => 'familiar',
                'name' => json_encode(['es' => 'Familiar', 'en' => 'Family', 'pt' => 'Familiar']),
                'description' => json_encode(['es' => 'Hasta 6 miembros, 2 sesiones grupales al mes', 'en' => 'Up to 6 members, 2 group sessions per month', 'pt' => 'Até 6 membros, 2 sessões em grupo por mês']),
                'features' => json_encode(['Hasta 6 miembros', '2 sesiones grupales/mes', 'Perfiles OCEAN por miembro', 'Chat grupal']),
                'price_monthly' => 1299.00,
                'price_yearly' => 12999.00,
                'max_sessions_per_month' => 6,
                'max_members' => 6,
                'max_mentors' => 1,
            ],
            [
                'client_type' => 'grupal',
                'name' => json_encode(['es' => 'Grupal', 'en' => 'Group', 'pt' => 'Grupal']),
                'description' => json_encode(['es' => 'Cohortes de 5-20 personas con mentor dedicado', 'en' => '5-20 person cohorts with dedicated mentor', 'pt' => 'Coortes de 5-20 pessoas com mentor dedicado']),
                'features' => json_encode(['5-20 miembros', '4 sesiones grupales/mes', 'Foro del cohorte', 'Progreso grupal']),
                'price_monthly' => 2499.00,
                'price_yearly' => 24999.00,
                'max_sessions_per_month' => 4,
                'max_members' => 20,
                'max_mentors' => 1,
            ],
            [
                'client_type' => 'empresa',
                'name' => json_encode(['es' => 'Startup', 'en' => 'Startup', 'pt' => 'Startup']),
                'description' => json_encode(['es' => 'Hasta 20 empleados, dashboard corporativo', 'en' => 'Up to 20 employees, corporate dashboard', 'pt' => 'Até 20 funcionários, painel corporativo']),
                'features' => json_encode(['Hasta 20 empleados', 'Dashboard corporativo', 'Reportes', 'Matching personalizado']),
                'price_monthly' => 4999.00,
                'price_yearly' => 49999.00,
                'max_sessions_per_month' => 40,
                'max_members' => 20,
                'max_mentors' => 5,
            ],
            [
                'client_type' => 'empresa',
                'name' => json_encode(['es' => 'Corporate', 'en' => 'Corporate', 'pt' => 'Corporate']),
                'description' => json_encode(['es' => 'Hasta 100 empleados, todo incluido', 'en' => 'Up to 100 employees, all included', 'pt' => 'Até 100 funcionários, tudo incluído']),
                'features' => json_encode(['Hasta 100 empleados', 'Dashboard avanzado', 'API', 'Soporte prioritario', 'Certificados blockchain']),
                'price_monthly' => 14999.00,
                'price_yearly' => 149999.00,
                'max_sessions_per_month' => 200,
                'max_members' => 100,
                'max_mentors' => 20,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(
                ['name' => $plan['name'], 'client_type' => $plan['client_type']],
                $plan
            );
        }
    }
}
