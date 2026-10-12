<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'legal_name' => ['sometimes', 'string', 'max:200'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'industry' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'in:startup,sme,enterprise'],
            'website' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'plan_id' => [
                'nullable',
                Rule::prohibitedIf(fn () => $this->filled('plan_id') && !$this->user()?->isAdmin()),
                Rule::exists('plans', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->where('client_type', 'empresa')),
            ],
        ];
    }
}
