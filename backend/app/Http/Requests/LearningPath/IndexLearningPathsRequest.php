<?php

namespace App\Http\Requests\LearningPath;

use Illuminate\Foundation\Http\FormRequest;

class IndexLearningPathsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'client_type' => ['nullable', 'string', 'in:personal,familiar,grupal,empresa'],
            'personality_type' => ['nullable', 'string', 'max:50'],
        ];
    }
}
