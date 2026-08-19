<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::withTrashed()->firstOrNew(['email' => 'admin@mis.com']);

        if ($admin->trashed()) {
            $admin->restore();
        }

        $admin->forceFill([
            'name' => 'Super Admin',
            'last_name' => 'MIS',
            'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'Password123!')),
            'sex' => 'N',
            'birth_date' => null,
            'client_type' => null,
            'role' => UserRole::SuperAdmin->value,
            'locale' => 'es',
            'is_active' => true,
        ])->save();

        $admin->syncApplicationRole(UserRole::SuperAdmin->value);
    }
}
