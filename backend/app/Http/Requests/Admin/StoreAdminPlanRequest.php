<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        if (!$this->isMethod('post')) {
            return [];
        }

        return [
            'client_type' => ['required', 'string', 'in:personal,familiar,grupal,empresa'],
            'name' => ['required', 'array'],
            'name.es' => ['required', 'string'],
            'name.en' => ['required', 'string'],
            'name.pt' => ['required', 'string'],
            'description' => ['nullable', 'array'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['required', 'numeric', 'min:0'],
            'max_sessions_per_month' => ['required', 'integer', 'min:1'],
            'max_members' => ['required', 'integer', 'min:1'],
            'max_mentors' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
        ];
    }
}
