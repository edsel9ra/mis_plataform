<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'test_version' => ['sometimes', 'string', 'in:ipip-neo-120,ipip-neo-300'],
            'answers' => ['nullable', 'array'],
            'results' => ['nullable', 'array'],
            'raw_scores' => ['nullable', 'array'],
            'completed_at' => ['nullable', 'date'],
        ];
    }
}
