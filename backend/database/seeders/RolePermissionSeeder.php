<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view_dashboard', 'view_mentors', 'view_sessions', 'view_messages',
            'view_learning_paths', 'view_certificates', 'view_profile',
            'manage_relationships', 'manage_sessions', 'manage_messages',
            'manage_company', 'manage_employees', 'manage_plans',
            'manage_users', 'manage_mentors', 'manage_assessments', 'manage_all',
            'view_reports', 'issue_certificates', 'revoke_certificates',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $roles = [
            UserRole::SuperAdmin->value => ['manage_all', 'view_reports'],
            UserRole::Admin->value => [
                'manage_users', 'manage_mentors', 'manage_plans',
                'manage_sessions', 'manage_assessments',
                'view_reports', 'issue_certificates', 'revoke_certificates',
                'view_dashboard', 'view_sessions', 'view_messages',
            ],
            UserRole::CompanyAdmin->value => [
                'manage_company', 'manage_employees', 'manage_relationships',
                'view_dashboard', 'view_mentors', 'view_sessions',
                'view_messages', 'view_certificates', 'view_reports',
            ],
            UserRole::Employee->value => [
                'view_dashboard', 'view_sessions', 'view_messages',
                'view_learning_paths', 'view_certificates', 'view_profile',
            ],
            UserRole::Mentor->value => [
                'view_dashboard', 'view_mentors', 'view_sessions',
                'view_messages', 'manage_sessions', 'manage_messages',
                'manage_relationships', 'view_learning_paths', 'view_certificates',
            ],
            UserRole::Mentee->value => [
                'view_dashboard', 'view_mentors', 'view_sessions',
                'view_messages', 'view_learning_paths', 'view_certificates', 'view_profile',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }
    }
}
