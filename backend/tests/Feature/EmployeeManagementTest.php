<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;

uses()->group('companies');

function managedCompanyWithEmployee(string $status = 'invited'): array
{
    $admin = User::factory()->create([
        'client_type' => 'empresa',
        'role' => 'company_admin',
    ]);
    $company = Company::create([
        'legal_name' => 'Acme Consulting',
        'admin_id' => $admin->id,
    ]);
    $admin->update(['company_id' => $company->id]);

    $employee = Employee::create([
        'company_id' => $company->id,
        'user_id' => User::factory()->create([
            'client_type' => 'empresa',
            'role' => 'employee',
            'company_id' => $company->id,
            'is_active' => $status === 'active',
        ])->id,
        'status' => $status,
        'position' => 'Analyst',
        'department' => 'Operations',
    ]);

    return [$admin, $company, $employee];
}

function outsiderAdmin(): User
{
    $admin = User::factory()->create([
        'client_type' => 'empresa',
        'role' => 'company_admin',
    ]);
    $otherCompany = Company::create([
        'legal_name' => 'Other Corp',
        'admin_id' => $admin->id,
    ]);
    $admin->update(['company_id' => $otherCompany->id]);

    return $admin;
}

it('updates an employee position and department', function () {
    [$admin, , $employee] = managedCompanyWithEmployee();

    $this->withToken($admin->createToken('test')->plainTextToken)
        ->putJson("/api/v1/employees/{$employee->id}", [
            'position' => 'Lead Analyst',
            'department' => 'Finance',
        ])
        ->assertOk()
        ->assertJsonPath('position', 'Lead Analyst')
        ->assertJsonPath('department', 'Finance');

    expect($employee->fresh()->position)->toBe('Lead Analyst')
        ->and($employee->fresh()->department)->toBe('Finance');
});

it('clears nullable employee fields when sent empty', function () {
    [$admin, , $employee] = managedCompanyWithEmployee();

    $this->withToken($admin->createToken('test')->plainTextToken)
        ->putJson("/api/v1/employees/{$employee->id}", [
            'position' => null,
            'department' => null,
        ])
        ->assertOk()
        ->assertJsonPath('position', null)
        ->assertJsonPath('department', null);
});

it('activates an invited employee and enables their user account', function () {
    [$admin, , $employee] = managedCompanyWithEmployee('invited');

    $this->withToken($admin->createToken('test')->plainTextToken)
        ->postJson("/api/v1/employees/{$employee->id}/activate")
        ->assertOk()
        ->assertJsonPath('status', 'active');

    expect($employee->fresh()->activated_at)->not->toBeNull()
        ->and($employee->user->fresh()->is_active)->toBeTrue();
});

it('activates an inactive employee and enables their user account', function () {
    [$admin, , $employee] = managedCompanyWithEmployee('inactive');

    $this->withToken($admin->createToken('test')->plainTextToken)
        ->postJson("/api/v1/employees/{$employee->id}/activate")
        ->assertOk()
        ->assertJsonPath('status', 'active');

    expect($employee->user->fresh()->is_active)->toBeTrue();
});

it('deactivates an active employee and disables their user account', function () {
    [$admin, , $employee] = managedCompanyWithEmployee('active');

    $this->withToken($admin->createToken('test')->plainTextToken)
        ->deleteJson("/api/v1/employees/{$employee->id}")
        ->assertOk()
        ->assertJsonPath('status', 'inactive');

    expect($employee->user->fresh()->is_active)->toBeFalse();
});

it('forbids another company admin from updating, activating or deactivating', function () {
    [, , $employee] = managedCompanyWithEmployee('active');
    $outsider = outsiderAdmin();
    $token = $outsider->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->putJson("/api/v1/employees/{$employee->id}", ['position' => 'X'])
        ->assertForbidden();
    $this->withToken($token)
        ->postJson("/api/v1/employees/{$employee->id}/activate")
        ->assertForbidden();
    $this->withToken($token)
        ->deleteJson("/api/v1/employees/{$employee->id}")
        ->assertForbidden();
});
