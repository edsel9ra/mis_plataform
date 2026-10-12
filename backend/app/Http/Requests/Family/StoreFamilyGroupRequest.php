<?php

namespace App\Http\Requests\Family;

use Illuminate\Foundation\Http\FormRequest;

class StoreFamilyGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'family_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ];
    }
}
