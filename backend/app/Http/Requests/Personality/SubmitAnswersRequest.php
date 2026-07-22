<?php

namespace App\Http\Requests\Personality;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:120', 'max:300'],
            'answers.*.id_question' => ['required', 'integer'],
            'answers.*.id_select' => ['required', 'integer', 'between:1,5'],
            'test_version' => ['required', 'string', 'in:ipip-neo-120,ipip-neo-300'],
            'age' => ['nullable', 'integer', 'min:12', 'max:120'],
            'sex' => ['nullable', 'string', 'in:M,F,N'],
        ];
    }
}
