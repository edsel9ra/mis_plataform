<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SUPER_ADMIN_PASSWORD');

        if (app()->isProduction() && empty($password)) {
            throw new RuntimeException(
                'SUPER_ADMIN_PASSWORD must be set when seeding in production; refusing to seed the admin account with a default password.'
            );
        }

        $admin = User::withTrashed()->firstOrNew(['email' => 'admin@mis.com']);

        if ($admin->trashed()) {
            $admin->restore();
        }

        $attributes = [
            'name' => 'Super Admin',
            'last_name' => 'MIS',
            'sex' => 'N',
            'birth_date' => null,
            'client_type' => null,
            'role' => UserRole::SuperAdmin->value,
            'locale' => 'es',
            'is_active' => true,
        ];

        if (! $admin->exists) {
            $attributes['password'] = Hash::make($password ?: 'Password123!');
        }

        $admin->forceFill($attributes)->save();

        $admin->syncApplicationRole(UserRole::SuperAdmin->value);
    }
}
