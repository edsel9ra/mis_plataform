<?php

namespace App\Services;

class PersonalityInterpretationService
{
    private const IMPROVEMENT_THRESHOLD = 51;

    /** @return array<string, array{trait: string, score: float|int, level: string, needs_improvement: bool}> */
    public function interpret(array $factors): array
    {
        $labels = [
            'O' => ['es' => 'Apertura a la Experiencia', 'en' => 'Openness', 'pt' => 'Abertura para Experiência'],
            'C' => ['es' => 'Responsabilidad', 'en' => 'Conscientiousness', 'pt' => 'Conscienciosidade'],
            'E' => ['es' => 'Extraversión', 'en' => 'Extraversion', 'pt' => 'Extroversão'],
            'A' => ['es' => 'Amabilidad', 'en' => 'Agreeableness', 'pt' => 'Amabilidade'],
            'N' => ['es' => 'Neuroticismo', 'en' => 'Neuroticism', 'pt' => 'Neuroticismo'],
        ];
        $locale = app()->getLocale();
        $interpretation = [];

        foreach ($labels as $key => $translatedLabels) {
            if (!isset($factors[$key]) || !is_numeric($factors[$key])) {
                continue;
            }

            $score = $factors[$key] + 0;
            $interpretation[$key] = [
                'trait' => $translatedLabels[$locale] ?? $translatedLabels['es'],
                'score' => $score,
                'needs_improvement' => $score < self::IMPROVEMENT_THRESHOLD,
                'level' => match (true) {
                    $score >= 70 => __('personality.level_high'),
                    $score >= 40 => __('personality.level_medium'),
                    default => __('personality.level_low'),
                },
            ];
        }

        return $interpretation;
    }
}
