<?php

namespace App\Http\Controllers\Api;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeController
{
    public function index(string $companyId): JsonResponse
    {
        $employees = Employee::where('company_id', $companyId)
            ->with('user')
            ->paginate(20);

        return response()->json($employees);
    }

    public function invite(Request $request, string $companyId): JsonResponse
    {
        $company = Company::findOrFail($companyId);

        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
        ]);

        $tempPassword = Str::password(12);

        $user = User::create([
            'name' => $validated['name'],
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make($tempPassword),
            'client_type' => 'empresa',
            'role' => 'employee',
            'company_id' => $company->id,
        ]);

        $employee = Employee::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'position' => $validated['position'] ?? null,
            'department' => $validated['department'] ?? null,
            'status' => 'invited',
            'invited_at' => now(),
        ]);

        // TODO: Send invitation email with temp password

        return response()->json([
            'employee' => $employee->load('user'),
            'temporary_password' => $tempPassword,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        $validated = $request->validate([
            'position' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'manager_name' => ['nullable', 'string', 'max:100'],
        ]);

        $employee->update($validated);

        return response()->json($employee->load('user'));
    }

    public function activate(string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->update([
            'status' => 'active',
            'activated_at' => now(),
        ]);

        $employee->user->update(['is_active' => true]);

        return response()->json($employee->load('user'));
    }

    public function deactivate(string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->update(['status' => 'inactive']);

        $employee->user->update(['is_active' => false]);

        return response()->json($employee->load('user'));
    }
}
