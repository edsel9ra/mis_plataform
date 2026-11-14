<?php

namespace App\Http\Requests\Evaluation;

use Illuminate\Foundation\Http\FormRequest;

class SubmitEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required'],
            'answers.*.option_index' => ['required', 'integer', 'min:0'],
            'time_spent_seconds' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
