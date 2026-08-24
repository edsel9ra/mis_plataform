<?php

namespace App\Http\Requests\Family;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'plan_id' => [
                'nullable',
                Rule::prohibitedIf(fn () => $this->filled('plan_id') && !$this->user()?->isAdmin()),
                Rule::exists('plans', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->where('client_type', 'familiar')),
            ],
        ];
    }
}
