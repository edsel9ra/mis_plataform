<?php

namespace App\Http\Requests\Cohort;

use Illuminate\Foundation\Http\FormRequest;

class StoreCohortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'max_members' => ['sometimes', 'integer', 'min:2', 'max:100'],
        ];
    }
}
