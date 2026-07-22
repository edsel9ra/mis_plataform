<?php

namespace App\Actions;

use App\Models\PersonalityAssessment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class SubmitPersonalityTestAction
{
    public function execute(User $user, array $validated): PersonalityAssessment
    {
        $answersFormatted = [];
        foreach ($validated['answers'] as $answer) {
            $answersFormatted[$answer['id_question']] = $answer['id_select'];
        }

        $age = $validated['age'] ?? ($user->birth_date?->age) ?? 30;
        $sex = $validated['sex'] ?? $user->sex ?? 'N';

        $response = Http::timeout(30)->post(
            config('services.matching.url') . '/api/v1/personality/score',
            [
                'answers' => $answersFormatted,
                'test_version' => $validated['test_version'],
                'age' => $age,
                'sex' => $sex,
            ]
        );

        $results = $response->json();

        return PersonalityAssessment::create([
            'user_id' => $user->id,
            'test_version' => $validated['test_version'],
            'answers' => $answersFormatted,
            'results' => $results,
            'raw_scores' => $results['raw_scores'] ?? null,
            'completed_at' => now(),
        ]);
    }
}
