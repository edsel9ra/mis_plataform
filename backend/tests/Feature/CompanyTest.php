<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;

uses()->group('companies');

it('onboards an empresa mentee as a company administrator', function () {
    $user = User::factory()->create([
        'client_type' => 'empresa',
        'role' => 'mentee',
        'company_id' => null,
    ]);

    $response = $this->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/v1/companies', [
            'legal_name' => 'Acme Consulting',
            'industry' => 'Technology',
            'size' => 'sme',
        ]);

    $response->assertCreated()
        ->assertJsonPath('legal_name', 'Acme Consulting');

    $user->refresh();
    expect($user->role)->toBe('company_admin')
        ->and($user->company_id)->not->toBeNull();
    $this->assertDatabaseHas('companies', [
        'id' => $user->company_id,
        'admin_id' => $user->id,
    ]);
});

it('returns employee totals grouped by current status', function () {
    $admin = User::factory()->create([
        'client_type' => 'empresa',
        'role' => 'company_admin',
    ]);
    $company = Company::create([
        'legal_name' => 'Acme Consulting',
        'admin_id' => $admin->id,
    ]);
    $admin->update(['company_id' => $company->id]);

    foreach (['active', 'active', 'invited', 'inactive'] as $status) {
        Employee::create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create([
                'client_type' => 'empresa',
                'role' => 'employee',
                'company_id' => $company->id,
            ])->id,
            'status' => $status,
        ]);
    }

    $this->withToken($admin->createToken('test')->plainTextToken)
        ->getJson("/api/v1/companies/{$company->id}/employees")
        ->assertOk()
        ->assertJsonPath('meta.total', 4)
        ->assertJsonPath('status_counts.active', 2)
        ->assertJsonPath('status_counts.invited', 1)
        ->assertJsonPath('status_counts.inactive', 1);
});
