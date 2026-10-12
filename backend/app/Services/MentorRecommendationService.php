<?php

namespace App\Services;

use App\Http\Resources\RecommendationMentorResource;
use App\Http\Resources\RecommendationSessionResource;
use App\Models\Assessment;
use App\Models\MentorshipRelationship;
use App\Models\User;
use Illuminate\Http\Request;

class MentorRecommendationService
{
    /** @return array<string, mixed> */
    public function forTarget(User $target, MentorshipRelationship $relationship, Request $request): array
    {
        $assessment = $target->personalityAssessment;
        $ocean = $assessment?->completed_at ? $this->safeFactors($assessment->getOceanScores()) : [];
        $ocean = count($ocean) === 5 ? $ocean : null;

        $mentors = collect();
        if ($ocean !== null) {
            $mentors = User::query()
                ->byRole('mentor')
                ->active()
                ->where('client_type', $relationship->type)
                ->where('id', '!=', $target->id)
                ->whereHas('personalityAssessment', fn ($query) => $query->whereNotNull('completed_at'))
                ->with(['personalityAssessment', 'skills.skillTag'])
                ->withCount(['mentorRelationships as active_mentees_count' => fn ($query) => $query->active()])
                ->get()
                ->each(function (User $mentor) use ($ocean) {
                    $mentor->compatibility_score = $this->compatibility(
                        $ocean,
                        $this->safeFactors($mentor->personalityAssessment?->getOceanScores() ?? []),
                    );
                })
                ->sortByDesc('compatibility_score')
                ->take(6)
                ->values();
        }

        $sessions = $relationship->sessions()
            ->upcoming()
            ->orderBy('scheduled_at')
            ->take(10)
            ->get();

        return [
            'recommended_mentors' => RecommendationMentorResource::collection($mentors)->resolve($request),
            'recommended_sessions' => RecommendationSessionResource::collection($sessions)->resolve($request),
            'recommended_evaluations' => $this->evaluations($relationship->type, $ocean),
        ];
    }

    private function compatibility(array $target, array $mentor): float
    {
        if (count($target) !== 5 || count($mentor) !== 5) {
            return 0;
        }

        $scores = [];
        foreach (['O', 'C', 'E', 'A', 'N'] as $dimension) {
            $difference = abs($target[$dimension] - $mentor[$dimension]);
            $scores[] = in_array($dimension, ['O', 'E', 'N'], true)
                ? max(0, 100 - $difference)
                : ($difference > 30 ? max(0, 100 - $difference) : 70 + (30 - $difference) / 3);
        }

        return round(array_sum($scores) / count($scores), 1);
    }

    /** @return array<int, array<string, mixed>> */
    private function evaluations(string $type, ?array $ocean): array
    {
        $slugs = $ocean === null ? [] : $this->evaluationSlugs($ocean);
        $query = Assessment::query()
            ->where('is_active', true)
            ->where(function ($query) use ($type) {
                $query->whereNull('client_types')
                    ->orWhereJsonLength('client_types', 0)
                    ->orWhereJsonContains('client_types', $type);
            });

        if ($slugs !== []) {
            $query->whereIn('slug', $slugs);
        }

        $evaluations = $query->latest()->take(6)->get();

        if ($evaluations->isEmpty() && $slugs !== []) {
            return $this->evaluations($type, null);
        }

        return $evaluations->map(function (Assessment $evaluation) {
            $locale = app()->getLocale();

            return [
                'id' => $evaluation->id,
                'title' => $evaluation->title[$locale] ?? $evaluation->title['es'] ?? $evaluation->slug,
                'description' => $evaluation->description[$locale] ?? $evaluation->description['es'] ?? '',
                'type' => $evaluation->category ?? 'desarrollo',
            ];
        })->values()->all();
    }

    /** @return array<int, string> */
    private function evaluationSlugs(array $ocean): array
    {
        $slugs = [];
        if ($ocean['O'] >= 60) {
            $slugs[] = 'creatividad';
        }
        if ($ocean['C'] >= 60) {
            $slugs[] = 'liderazgo';
        }
        if ($ocean['E'] >= 60 || $ocean['A'] >= 60 || $ocean['E'] < 40) {
            $slugs[] = 'inteligencia-emocional';
        }
        if ($ocean['N'] >= 60) {
            $slugs[] = 'estres-burnout';
        }
        if ($ocean['C'] < 40) {
            $slugs[] = 'motivacion';
        }

        return array_values(array_unique($slugs ?: ['bienestar-integral']));
    }

    /** @return array<string, float|int> */
    private function safeFactors(array $factors): array
    {
        $safe = [];
        foreach (['O', 'C', 'E', 'A', 'N'] as $key) {
            if (isset($factors[$key]) && is_numeric($factors[$key])) {
                $safe[$key] = $factors[$key] + 0;
            }
        }

        return $safe;
    }
}
