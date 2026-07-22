<?php

namespace App\Http\Controllers\Api;

use App\Actions\SubmitPersonalityTestAction;
use App\Http\Requests\Personality\SubmitAnswersRequest;
use App\Http\Resources\PersonalityAssessmentResource;
use App\Models\PersonalityAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PersonalityController
{
    public function __construct(
        private SubmitPersonalityTestAction $submitTest,
    ) {}

    public function startTest(Request $request): JsonResponse
    {
        $user = $request->user();

        $existing = PersonalityAssessment::where('user_id', $user->id)->first();
        if ($existing) {
            $factors = $existing->results['factors'] ?? [];
            if ($existing->completed_at && !empty($factors)) {
                return response()->json([
                    'message' => __('personality.test_already_completed'),
                    'assessment' => new PersonalityAssessmentResource($existing),
                ]);
            }

            $existing->delete();
        }

        $locale = $user->locale ?? app()->getLocale();

        $data = $this->getTestQuestions('ipip-neo-120');

        $questions = $data['questions_' . $locale] ?? $data['questions_es'] ?? [];
        $select = $data[$locale]['select'] ?? $data['es']['select'] ?? [];

        return response()->json([
            'test_version' => 'ipip-neo-120',
            'locale' => $locale,
            'total_questions' => count($questions),
            'questions' => $questions,
            'select' => $select,
        ]);
    }

    public function submitAnswers(SubmitAnswersRequest $request): JsonResponse
    {
        $user = $request->user();

        $existing = PersonalityAssessment::where('user_id', $user->id)->first();
        if ($existing && $existing->completed_at) {
            $factors = $existing->results['factors'] ?? [];
            if (!empty($factors)) {
                return response()->json([
                    'message' => __('personality.test_already_completed'),
                    'assessment' => new PersonalityAssessmentResource($existing),
                ], 409);
            }
        }

        if ($existing) {
            $existing->delete();
        }

        try {
            $assessment = $this->submitTest->execute($user, $request->validated());
        } catch (\Exception $e) {
            return response()->json([
                'message' => __('personality.scoring_error'),
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(
            new PersonalityAssessmentResource($assessment),
            201,
        );
    }

    public function report(Request $request): JsonResponse
    {
        $assessment = PersonalityAssessment::where('user_id', $request->user()->id)->firstOrFail();

        return response()->json([
            'test_version' => $assessment->test_version,
            'completed_at' => $assessment->completed_at,
            'factors' => $assessment->results['factors'] ?? [],
            'facets' => $assessment->results['facets'] ?? [],
            'interpretation' => $this->interpretResults($assessment->results['factors'] ?? []),
        ]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'test_version' => ['required', 'string', 'in:ipip-neo-120,ipip-neo-300'],
            'age' => ['required', 'integer'],
            'sex' => ['required', 'string', 'in:M,F,N'],
        ]);

        try {
            $response = Http::timeout(30)->post(
                config('services.matching.url') . '/api/v1/personality/score',
                $validated,
            );
            $results = $response->json();
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json($results);
    }

    private function getTestQuestions(string $version): array
    {
        $path = resource_path("data/big-five/{$version}/questions.json");

        if (!file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true);
    }

    private function interpretResults(array $factors): array
    {
        $interpretation = [];

        $labels = [
            'O' => ['es' => 'Apertura a la Experiencia', 'en' => 'Openness', 'pt' => 'Abertura para Experiência'],
            'C' => ['es' => 'Responsabilidad', 'en' => 'Conscientiousness', 'pt' => 'Conscienciosidade'],
            'E' => ['es' => 'Extraversión', 'en' => 'Extraversion', 'pt' => 'Extroversão'],
            'A' => ['es' => 'Amabilidad', 'en' => 'Agreeableness', 'pt' => 'Amabilidade'],
            'N' => ['es' => 'Neuroticismo', 'en' => 'Neuroticism', 'pt' => 'Neuroticismo'],
        ];

        $locale = app()->getLocale();

        foreach ($factors as $key => $value) {
            $level = match (true) {
                $value >= 70 => __('personality.level_high'),
                $value >= 40 => __('personality.level_medium'),
                default => __('personality.level_low'),
            };

            $interpretation[$key] = [
                'trait' => $labels[$key][$locale] ?? $labels[$key]['es'],
                'score' => $value,
                'level' => $level,
            ];
        }

        return $interpretation;
    }
}
