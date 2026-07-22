<?php

namespace Database\Seeders;

use App\Models\SkillTag;
use Illuminate\Database\Seeder;

class SkillTagSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            ['name' => 'Liderazgo', 'category' => 'habilidades_blandas'],
            ['name' => 'Comunicación', 'category' => 'habilidades_blandas'],
            ['name' => 'Inteligencia Emocional', 'category' => 'habilidades_blandas'],
            ['name' => 'Resolución de Conflictos', 'category' => 'habilidades_blandas'],
            ['name' => 'Trabajo en Equipo', 'category' => 'habilidades_blandas'],
            ['name' => 'Pensamiento Crítico', 'category' => 'habilidades_blandas'],
            ['name' => 'Creatividad', 'category' => 'habilidades_blandas'],
            ['name' => 'Empatía', 'category' => 'habilidades_blandas'],
            ['name' => 'Planeación Estratégica', 'category' => 'profesional'],
            ['name' => 'Gestión de Proyectos', 'category' => 'profesional'],
            ['name' => 'Innovación', 'category' => 'profesional'],
            ['name' => 'Desarrollo de Carrera', 'category' => 'profesional'],
            ['name' => 'Productividad', 'category' => 'profesional'],
            ['name' => 'Networking', 'category' => 'profesional'],
            ['name' => 'Mindfulness', 'category' => 'bienestar'],
            ['name' => 'Bienestar Emocional', 'category' => 'bienestar'],
            ['name' => 'Gestión del Estrés', 'category' => 'bienestar'],
            ['name' => 'Equilibrio Vida-Trabajo', 'category' => 'bienestar'],
            ['name' => 'Parentalidad', 'category' => 'familiar'],
            ['name' => 'Comunicación Familiar', 'category' => 'familiar'],
            ['name' => 'Educación Infantil', 'category' => 'familiar'],
            ['name' => 'Cultura Organizacional', 'category' => 'empresarial'],
            ['name' => 'Gestión del Talento', 'category' => 'empresarial'],
            ['name' => 'Transformación Digital', 'category' => 'empresarial'],
        ];

        foreach ($skills as $skill) {
            SkillTag::firstOrCreate(
                ['name' => $skill['name']],
                ['category' => $skill['category']]
            );
        }
    }
}
