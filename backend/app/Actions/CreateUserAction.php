<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUserAction
{
    public function execute(array $data, ?string $role = null): User
    {
        $user = User::create([
            'name' => $data['name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'sex' => $data['sex'] ?? 'N',
            'birth_date' => $data['birth_date'] ?? null,
            'client_type' => $data['client_type'],
            'role' => $role ?? UserRole::Mentee->value,
            'locale' => $data['locale'] ?? app()->getLocale(),
        ]);

        $user->syncApplicationRole();

        return $user;
    }
}
