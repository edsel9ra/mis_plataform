<?php

namespace App\Http\Requests\Family;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFamilyGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'family_name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'plan_id' => ['nullable', 'exists:plans,id'],
        ];
    }
}
