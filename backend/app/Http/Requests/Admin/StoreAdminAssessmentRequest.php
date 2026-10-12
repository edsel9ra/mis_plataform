<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'test_version' => ['required', 'string', 'in:ipip-neo-120,ipip-neo-300'],
            'answers' => ['nullable', 'array'],
            'results' => ['nullable', 'array'],
            'raw_scores' => ['nullable', 'array'],
            'completed_at' => ['nullable', 'date'],
        ];
    }
}
