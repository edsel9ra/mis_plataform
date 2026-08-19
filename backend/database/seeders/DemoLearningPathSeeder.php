<?php

namespace Database\Seeders;

use App\Models\LearningPath;
use Illuminate\Database\Seeder;

class DemoLearningPathSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->paths() as $pathData) {
            $path = LearningPath::query()
                ->where('client_type', $pathData['client_type'])
                ->where('title->es', $pathData['title']['es'])
                ->first();

            if (!$path) {
                $path = new LearningPath();
            }

            $path->fill(collect($pathData)->except('modules')->all());
            $path->save();

            $path->modules()->delete();

            foreach ($pathData['modules'] as $index => $moduleData) {
                $module = $path->modules()->create([
                    'title' => $moduleData['title'],
                    'description' => $moduleData['description'],
                    'order' => $index + 1,
                    'estimated_minutes' => $moduleData['estimated_minutes'],
                ]);

                foreach ($moduleData['resources'] as $resourceIndex => $resource) {
                    $module->resources()->create($resource + ['order' => $resourceIndex + 1]);
                }
            }
        }
    }

    private function paths(): array
    {
        return [
            [
                'title' => ['es' => 'Inteligencia Emocional Practica', 'en' => 'Practical Emotional Intelligence', 'pt' => 'Inteligencia Emocional Pratica'],
                'description' => ['es' => 'Gestion de emociones y relaciones interpersonales efectivas con ejercicios aplicables.'],
                'personality_tags' => ['A', 'N'],
                'client_type' => 'personal',
                'level' => 'beginner',
                'estimated_hours' => 6,
                'is_active' => true,
                'modules' => $this->modules('Autoconciencia emocional', 'Regulacion y respuesta consciente', 'Plan personal de bienestar'),
            ],
            [
                'title' => ['es' => 'Resiliencia y Gestion del Estres', 'en' => 'Resilience and Stress Management', 'pt' => 'Resiliencia e Gestao do Estresse'],
                'description' => ['es' => 'Tecnicas de neurociencia para construir resiliencia personal y prevenir burnout.'],
                'personality_tags' => ['C', 'N'],
                'client_type' => 'personal',
                'level' => 'beginner',
                'estimated_hours' => 5,
                'is_active' => true,
                'modules' => $this->modules('Mapa de estresores', 'Recuperacion y energia', 'Rutinas de resiliencia'),
            ],
            [
                'title' => ['es' => 'Cohesion y Dinamica Familiar', 'en' => 'Family Cohesion and Dynamics', 'pt' => 'Coesao e Dinamica Familiar'],
                'description' => ['es' => 'Fortalece vinculos familiares desde la psicologia sistemica y la comunicacion clara.'],
                'personality_tags' => ['A', 'E'],
                'client_type' => 'familiar',
                'level' => 'beginner',
                'estimated_hours' => 7,
                'is_active' => true,
                'modules' => $this->modules('Diagnostico del sistema familiar', 'Conversaciones reparadoras', 'Acuerdos y seguimiento'),
            ],
            [
                'title' => ['es' => 'Liderazgo Transformacional', 'en' => 'Transformational Leadership', 'pt' => 'Lideranca Transformacional'],
                'description' => ['es' => 'Habilidades de liderazgo, delegacion y toma de decisiones basadas en neurociencia aplicada.'],
                'personality_tags' => ['C', 'E'],
                'client_type' => 'grupal',
                'level' => 'intermediate',
                'estimated_hours' => 8,
                'is_active' => true,
                'modules' => $this->modules('Estilo de liderazgo', 'Influencia y vision compartida', 'Decision bajo presion'),
            ],
            [
                'title' => ['es' => 'Clima Organizacional y Cultura', 'en' => 'Organizational Climate and Culture', 'pt' => 'Clima Organizacional e Cultura'],
                'description' => ['es' => 'Diagnostico y mejora del ambiente laboral, cultura y gestion del talento.'],
                'personality_tags' => ['A', 'C'],
                'client_type' => 'empresa',
                'level' => 'advanced',
                'estimated_hours' => 10,
                'is_active' => true,
                'modules' => $this->modules('Lectura del clima actual', 'Rituales de reconocimiento', 'Plan de cultura medible'),
            ],
        ];
    }

    private function modules(string ...$titles): array
    {
        return array_map(fn (string $title, int $index) => [
            'title' => $title,
            'description' => 'Modulo guiado con practica, reflexion y recurso aplicado.',
            'estimated_minutes' => 90 + ($index * 20),
            'resources' => [
                [
                    'type' => 'document',
                    'title' => ['es' => "Guia: {$title}"],
                    'description' => ['es' => 'Material de trabajo para la sesion y seguimiento semanal.'],
                    'url' => 'https://mis-project.local/resources/demo',
                    'is_free' => true,
                ],
                [
                    'type' => 'exercise',
                    'title' => ['es' => "Ejercicio aplicado: {$title}"],
                    'description' => ['es' => 'Actividad breve para transferir el aprendizaje al contexto real.'],
                    'url' => 'https://mis-project.local/resources/demo-exercise',
                    'is_free' => false,
                ],
            ],
        ], $titles, array_keys($titles));
    }
}
