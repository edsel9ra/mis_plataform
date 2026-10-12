<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminMentorRequest extends FormRequest
{
    use NormalizesEmail;

    protected function prepareForValidation(): void
    {
        $this->normalizeEmailInput();
    }

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($this->route('id'))],
            'password' => ['sometimes', 'string', 'min:8'],
            'sex' => ['sometimes', 'string', 'in:M,F,N'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'client_type' => ['sometimes', 'string', 'in:personal,familiar,grupal,empresa'],
            'locale' => ['sometimes', 'string', 'in:es,en,pt'],
        ];
    }
}
