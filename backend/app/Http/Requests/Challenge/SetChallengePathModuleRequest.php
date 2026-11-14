<?php

namespace App\Http\Requests\Challenge;

use Illuminate\Foundation\Http\FormRequest;

class SetChallengePathModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'path_module_id' => ['present', 'nullable', 'uuid'],
        ];
    }
}
