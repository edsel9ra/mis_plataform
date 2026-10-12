<?php

namespace App\Http\Requests\Mentor;

use Illuminate\Foundation\Http\FormRequest;

class StoreObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isMentor();
    }

    protected function prepareForValidation(): void
    {
        $observation = $this->input('mentor_observation');
        if (is_array($observation)) {
            $attention = $observation['attention_score'] ?? $observation['focus'] ?? null;
            if (is_numeric($attention)) {
                $attention = (float) $attention > 5
                    ? (int) round(((float) $attention) / 20, 0, PHP_ROUND_HALF_UP)
                    : (int) $attention;
                $attention = max(1, min(5, $attention));
            } else {
                $dimensionScores = collect($observation)
                    ->only(['focus', 'regulation', 'stress', 'energy', 'motivation'])
                    ->filter(fn ($value) => is_numeric($value));
                $attention = $dimensionScores->isNotEmpty()
                    ? max(1, min(5, (int) round($dimensionScores->avg() / 20, 0, PHP_ROUND_HALF_UP)))
                    : 1;
            }

            $this->merge([
                'attention_score' => $attention,
                'engagement_score' => $observation['engagement_score'] ?? null,
                'dimensions' => collect($observation)
                    ->only(['focus', 'regulation', 'stress', 'energy', 'motivation'])
                    ->all(),
                'note' => $observation['note'] ?? $observation['notes'] ?? null,
                'observed_at' => $observation['observed_at'] ?? null,
            ]);
        }

        if (!$this->has('attention_score') && $this->has('attention')) {
            $this->merge(['attention_score' => $this->input('attention')]);
        }
    }

    public function rules(): array
    {
        return [
            'attention_score' => ['required', 'integer', 'between:1,5'],
            'engagement_score' => ['nullable', 'integer', 'between:1,5'],
            'note' => ['nullable', 'string', 'max:10000'],
            'challenge_id' => ['nullable', 'uuid'],
            'observed_at' => ['nullable', 'date', 'before_or_equal:now'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.focus' => ['nullable', 'numeric', 'between:0,100'],
            'dimensions.regulation' => ['nullable', 'numeric', 'between:0,100'],
            'dimensions.stress' => ['nullable', 'numeric', 'between:0,100'],
            'dimensions.energy' => ['nullable', 'numeric', 'between:0,100'],
            'dimensions.motivation' => ['nullable', 'numeric', 'between:0,100'],
            'mentor_observation' => ['nullable', 'array'],
        ];
    }
}
