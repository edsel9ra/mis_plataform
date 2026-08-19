<?php

namespace App\Http\Requests\LearningPath;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLearningProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'progress' => ['required', 'numeric', 'between:0,100'],
            'module_id' => ['nullable', 'exists:path_modules,id'],
        ];
    }
}
