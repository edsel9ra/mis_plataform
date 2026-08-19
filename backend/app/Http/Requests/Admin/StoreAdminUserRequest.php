<?php

namespace App\Http\Requests\Admin;

use App\Enums\ClientType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->isPlatformAdminRole($this->input('role'))) {
            $this->merge(['client_type' => null]);
        }
    }

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
            'client_type' => [
                Rule::requiredIf(fn () => !$this->isPlatformAdminRole($this->input('role'))),
                'nullable',
                'string',
                Rule::in(array_column(ClientType::cases(), 'value')),
            ],
            'role' => ['required', 'string', Rule::in($this->assignableRoles())],
            'locale' => ['nullable', 'string', 'in:es,en,pt'],
        ];
    }

    private function assignableRoles(): array
    {
        $roles = array_map(fn (UserRole $role) => $role->value, UserRole::cases());

        if ($this->user()?->isSuperAdmin()) {
            return $roles;
        }

        return array_values(array_filter(
            $roles,
            fn (string $role) => $role !== UserRole::SuperAdmin->value,
        ));
    }

    private function isPlatformAdminRole(?string $role): bool
    {
        return in_array($role, [UserRole::SuperAdmin->value, UserRole::Admin->value], true);
    }
}
