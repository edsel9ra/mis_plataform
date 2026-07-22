<?php

namespace App\Http\Controllers\Api;

use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'legal_name' => ['required', 'string', 'max:200'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'industry' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'in:startup,sme,enterprise'],
            'website' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user = $request->user();
        $user->update(['client_type' => 'empresa', 'role' => 'company_admin']);

        $company = Company::create([
            ...$validated,
            'admin_id' => $user->id,
            'subscription_status' => 'trial',
        ]);

        $user->update(['company_id' => $company->id]);

        return response()->json($company, 201);
    }

    public function show(string $id): JsonResponse
    {
        $company = Company::with(['admin', 'employees.user', 'plan'])
            ->findOrFail($id);

        return response()->json($company);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $company = Company::findOrFail($id);

        $validated = $request->validate([
            'legal_name' => ['sometimes', 'string', 'max:200'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'industry' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'in:startup,sme,enterprise'],
            'website' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'plan_id' => ['nullable', 'exists:plans,id'],
        ]);

        $company->update($validated);

        return response()->json($company);
    }
}
