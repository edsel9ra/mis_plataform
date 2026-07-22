<?php

namespace App\Http\Requests\Auth;

use App\Enums\ClientType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'sex' => ['required', 'string', 'in:M,F,N'],
            'birth_date' => ['required', 'date', 'before:today'],
            'client_type' => ['required', 'string', 'in:' . implode(',', array_column(ClientType::cases(), 'value'))],
            'locale' => ['nullable', 'string', 'in:es,en,pt'],
        ];
    }
}
