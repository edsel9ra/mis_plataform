<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminMentorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'sex' => ['nullable', 'string', 'in:M,F,N'],
            'birth_date' => ['nullable', 'date'],
            'client_type' => ['required', 'string', 'in:personal,familiar,grupal,empresa'],
            'locale' => ['nullable', 'string', 'in:es,en,pt'],
        ];
    }
}
