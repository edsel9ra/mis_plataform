<?php

namespace App\Http\Requests\Personality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CalculatePersonalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'integer', 'between:1,5'],
            'test_version' => ['required', 'string', 'in:ipip-neo-120,ipip-neo-300'],
            'age' => ['required', 'integer', 'min:12', 'max:110'],
            'sex' => ['required', 'string', 'in:M,F,N'],
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

                $expected = $version === 'ipip-neo-120' ? 120 : 300;

                if (count($answers) !== $expected) {
                    $validator->errors()->add('answers', "The {$version} test requires exactly {$expected} answers.");
                }

                foreach (array_keys($answers) as $questionId) {
                    $questionId = filter_var($questionId, FILTER_VALIDATE_INT);
                    if ($questionId === false || $questionId < 1 || $questionId > $expected) {
                        $validator->errors()->add('answers', "Answer keys must be question ids between 1 and {$expected}.");
                        break;
                    }
                }
            },
        ];
    }
}
