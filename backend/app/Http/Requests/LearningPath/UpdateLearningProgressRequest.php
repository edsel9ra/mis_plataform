<?php

namespace App\Http\Requests\LearningPath;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'module_id' => [
                'nullable',
                Rule::exists('path_modules', 'id')->where(fn ($query) =>
                    $query->where('learning_path_id', $this->route('id'))
                ),
            ],
        ];
    }
}
