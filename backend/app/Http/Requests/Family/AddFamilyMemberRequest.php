<?php

namespace App\Http\Requests\Family;

use Illuminate\Foundation\Http\FormRequest;

class AddFamilyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:100'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'relationship' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
        ];
    }
}
