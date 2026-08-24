<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_type' => $this->client_type,
            'name' => $this->name,
            'description' => $this->localizedValue($this->description),
            'price_monthly' => (float) $this->price_monthly,
            'price_yearly' => (float) $this->price_yearly,
            'max_sessions_per_month' => $this->max_sessions_per_month,
            'max_members' => $this->max_members,
            'max_mentors' => $this->max_mentors,
            'features' => $this->localizedFeatures($this->features),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'subscriptions_count' => $this->whenCounted('subscriptions'),
        ];
    }

    private function localizedValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $locale = app()->getLocale();

        return $value[$locale] ?? $value['es'] ?? $value;
    }

    private function localizedFeatures(mixed $features): array
    {
        if (! is_array($features)) {
            return [];
        }

        $legacy = [
            '4 sesiones/mes' => ['en' => '4 sessions/month', 'pt' => '4 sessões/mês'],
            'Chat ilimitado' => ['en' => 'Unlimited chat', 'pt' => 'Chat ilimitado'],
            'Perfil OCEAN' => ['en' => 'OCEAN profile', 'pt' => 'Perfil OCEAN'],
            'Ruta de aprendizaje' => ['en' => 'Learning path', 'pt' => 'Trilha de aprendizagem'],
            '8 sesiones/mes' => ['en' => '8 sessions/month', 'pt' => '8 sessões/mês'],
            'Recursos avanzados' => ['en' => 'Advanced resources', 'pt' => 'Recursos avançados'],
            'Evaluaciones' => ['en' => 'Assessments', 'pt' => 'Avaliações'],
            'Certificado blockchain' => ['en' => 'Blockchain certificate', 'pt' => 'Certificado blockchain'],
            'Hasta 6 miembros' => ['en' => 'Up to 6 members', 'pt' => 'Até 6 membros'],
            '2 sesiones grupales/mes' => ['en' => '2 group sessions/month', 'pt' => '2 sessões em grupo/mês'],
            'Perfiles OCEAN por miembro' => ['en' => 'OCEAN profiles per member', 'pt' => 'Perfis OCEAN por membro'],
            'Chat grupal' => ['en' => 'Group chat', 'pt' => 'Chat em grupo'],
            '5-20 miembros' => ['en' => '5-20 members', 'pt' => '5-20 membros'],
            '4 sesiones grupales/mes' => ['en' => '4 group sessions/month', 'pt' => '4 sessões em grupo/mês'],
            'Foro del cohorte' => ['en' => 'Cohort forum', 'pt' => 'Fórum da coorte'],
            'Progreso grupal' => ['en' => 'Group progress', 'pt' => 'Progresso do grupo'],
            'Hasta 20 empleados' => ['en' => 'Up to 20 employees', 'pt' => 'Até 20 funcionários'],
            'Dashboard corporativo' => ['en' => 'Corporate dashboard', 'pt' => 'Painel corporativo'],
            'Reportes' => ['en' => 'Reports', 'pt' => 'Relatórios'],
            'Matching personalizado' => ['en' => 'Personalized matching', 'pt' => 'Matching personalizado'],
            'Hasta 100 empleados' => ['en' => 'Up to 100 employees', 'pt' => 'Até 100 funcionários'],
            'Dashboard avanzado' => ['en' => 'Advanced dashboard', 'pt' => 'Painel avançado'],
            'API' => ['en' => 'API', 'pt' => 'API'],
            'Soporte prioritario' => ['en' => 'Priority support', 'pt' => 'Suporte prioritário'],
            'Certificados blockchain' => ['en' => 'Blockchain certificates', 'pt' => 'Certificados blockchain'],
        ];
        $locale = app()->getLocale();

        return array_values(array_map(function (mixed $feature) use ($legacy, $locale): string {
            if (is_array($feature)) {
                return (string) ($feature[$locale] ?? $feature['es'] ?? reset($feature));
            }

            return (string) ($legacy[$feature][$locale] ?? $feature);
        }, $features));
    }
}
