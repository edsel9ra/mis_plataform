<?php

namespace App\Http\Requests\Mentor;

use Illuminate\Foundation\Http\FormRequest;

class IndexFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isMentor();
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'uuid'],
            'relationship_id' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
