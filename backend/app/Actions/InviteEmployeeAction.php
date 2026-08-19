<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class InviteEmployeeAction
{
    public function execute(Company $company, array $data): Employee
    {
        return DB::transaction(function () use ($company, $data) {
            $tempPassword = Str::password(32);

            $user = User::create([
                'name' => $data['name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'],
                'password' => Hash::make($tempPassword),
                'client_type' => 'empresa',
                'role' => UserRole::Employee->value,
                'company_id' => $company->id,
                'is_active' => false,
            ]);
            $user->syncApplicationRole(UserRole::Employee->value);

            $employee = Employee::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'position' => $data['position'] ?? null,
                'department' => $data['department'] ?? null,
                'status' => 'invited',
                'invited_at' => now(),
            ]);

            Password::sendResetLink(['email' => $user->email]);

            return $employee;
        });
    }
}
