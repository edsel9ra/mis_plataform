<?php

namespace App\Http\Requests\Cohort;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCohortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'max_members' => ['sometimes', 'integer', 'min:2', 'max:100'],
            'status' => ['sometimes', 'string', 'in:pending,active,completed'],
        ];
    }
}
