<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

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

it('invites an employee and sends a password reset notification', function () {
    Notification::fake();

    $admin = User::factory()->create([
        'client_type' => 'empresa',
        'role' => 'company_admin',
    ]);
    $company = Company::create([
        'legal_name' => 'Acme Consulting',
        'admin_id' => $admin->id,
    ]);
    $admin->update(['company_id' => $company->id]);

    $plan = Plan::create([
        'client_type' => 'empresa',
        'name' => ['es' => 'Empresa'],
        'price_monthly' => 100,
        'price_yearly' => 1000,
        'max_members' => 10,
        'is_active' => true,
    ]);
    Subscription::create([
        'subscriber_type' => 'company',
        'subscriber_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    $response = $this->withToken($admin->createToken('test')->plainTextToken)
        ->postJson("/api/v1/companies/{$company->id}/employees", [
            'name' => 'Jane',
            'last_name' => 'Worker',
            'email' => 'jane.worker@example.com',
            'position' => 'Analyst',
        ]);

    $response->assertCreated();

    $user = User::where('email', 'jane.worker@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('employee')
        ->and($user->is_active)->toBeFalse()
        ->and($user->company_id)->toBe($company->id);

    $this->assertDatabaseHas('employees', [
        'user_id' => $user->id,
        'company_id' => $company->id,
        'status' => 'invited',
    ]);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('rejects employee invitations when the company has no active plan', function () {
    Notification::fake();

    $admin = User::factory()->create([
        'client_type' => 'empresa',
        'role' => 'company_admin',
    ]);
    $company = Company::create([
        'legal_name' => 'Acme Consulting',
        'admin_id' => $admin->id,
    ]);
    $admin->update(['company_id' => $company->id]);

    $this->withToken($admin->createToken('test')->plainTextToken)
        ->postJson("/api/v1/companies/{$company->id}/employees", [
            'name' => 'Jane',
            'email' => 'jane.plan@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee']);
});

it('invites an employee when the company has an active plan', function () {
    Notification::fake();

    $admin = User::factory()->create([
        'client_type' => 'empresa',
        'role' => 'company_admin',
    ]);
    $company = Company::create([
        'legal_name' => 'Acme Consulting',
        'admin_id' => $admin->id,
    ]);
    $admin->update(['company_id' => $company->id]);

    $plan = Plan::create([
        'client_type' => 'empresa',
        'name' => ['es' => 'Empresa'],
        'price_monthly' => 100,
        'price_yearly' => 1000,
        'max_members' => 10,
        'is_active' => true,
    ]);
    Subscription::create([
        'subscriber_type' => 'company',
        'subscriber_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    $this->withToken($admin->createToken('test')->plainTextToken)
        ->postJson("/api/v1/companies/{$company->id}/employees", [
            'name' => 'Jane',
            'email' => 'jane.plan@example.com',
        ])
        ->assertCreated();
});
