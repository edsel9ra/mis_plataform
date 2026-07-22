<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@mis.com'],
            [
                'name' => 'Admin',
                'last_name' => 'MIS',
                'password' => Hash::make('password'),
                'sex' => 'N',
                'birth_date' => null,
                'client_type' => 'personal',
                'role' => 'admin',
                'locale' => 'es',
                'is_active' => true,
            ]
        );

        $admin->assignRole('admin');
    }
}
