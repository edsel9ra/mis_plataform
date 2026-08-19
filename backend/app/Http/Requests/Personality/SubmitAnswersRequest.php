<?php

namespace App\Http\Requests\Personality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array'],
            'answers.*.id_question' => ['required', 'integer'],
            'answers.*.id_select' => ['required', 'integer', 'between:1,5'],
            'test_version' => ['required', 'string', 'in:ipip-neo-120,ipip-neo-300'],
            'age' => ['nullable', 'integer', 'min:12', 'max:110'],
            'sex' => ['nullable', 'string', 'in:M,F,N'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $version = $this->input('test_version');
                $answers = $this->input('answers', []);

                if (!in_array($version, ['ipip-neo-120', 'ipip-neo-300'], true) || !is_array($answers)) {
                    return;
                }

                $age = $this->input('age') ?? $this->user()?->birth_date?->age ?? 30;
                if ($age < 12 || $age > 110) {
                    $validator->errors()->add('age', 'Age must be between 12 and 110 for IPIP-NEO scoring.');
                }

                $expected = $version === 'ipip-neo-120' ? 120 : 300;

                if (count($answers) !== $expected) {
                    $validator->errors()->add('answers', "The {$version} test requires exactly {$expected} answers.");
                }

                $seen = [];
                foreach ($answers as $index => $answer) {
                    if (!is_array($answer) || !array_key_exists('id_question', $answer)) {
                        continue;
                    }

                    $questionId = filter_var($answer['id_question'], FILTER_VALIDATE_INT);
                    if ($questionId === false) {
                        continue;
                    }

                    if ($questionId < 1 || $questionId > $expected) {
                        $validator->errors()->add("answers.{$index}.id_question", "Question id must be between 1 and {$expected}.");
                        continue;
                    }

                    if (isset($seen[$questionId])) {
                        $validator->errors()->add("answers.{$index}.id_question", 'Question ids must be unique.');
                    }

                    $seen[$questionId] = true;
                }
            },
        ];
    }
}
