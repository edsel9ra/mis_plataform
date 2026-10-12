<?php

namespace App\Http\Requests\Admin;

use App\Enums\ClientType;
use App\Enums\UserRole;
use App\Http\Requests\Concerns\NormalizesEmail;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    use NormalizesEmail;

    protected function prepareForValidation(): void
    {
        $this->normalizeEmailInput();

        $role = $this->input('role') ?? User::find($this->route('id'))?->role;

        if ($this->isPlatformAdminRole($role)) {
            $this->merge(['client_type' => null]);
        }
    }

    public function authorize(): bool
    {
        $actor = $this->user();
        $target = User::find($this->route('id'));

        if (!$actor?->isAdmin()) {
            return false;
        }

        if (!$actor->isSuperAdmin() && $target?->isSuperAdmin()) {
            return false;
        }

        return true;
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
            'client_type' => [
                Rule::requiredIf(fn () => $this->has('role') && !$this->isPlatformAdminRole($this->input('role'))),
                'nullable',
                'string',
                Rule::in(array_column(ClientType::cases(), 'value')),
            ],
            'role' => ['sometimes', 'string', Rule::in($this->assignableRoles())],
            'locale' => ['sometimes', 'string', 'in:es,en,pt'],
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
