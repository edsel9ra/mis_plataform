<?php

namespace App\Http\Requests\Family;

use Illuminate\Foundation\Http\FormRequest;

class StoreFamilyGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->client_type === 'familiar'
            && $user->role === 'mentee';
    }

    public function rules(): array
    {
        return [
            'family_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ];
    }
}
