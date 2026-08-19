<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController
{
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $company = DB::transaction(function () use ($request, $validated) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);

            abort_unless($user->client_type === 'empresa', 403);
            abort_if(
                $user->company_id || Company::where('admin_id', $user->id)->exists(),
                422,
                __('companies.already_exists'),
            );

            $user->update(['client_type' => 'empresa', 'role' => 'company_admin']);
            $user->syncApplicationRole('company_admin');

            $company = Company::create([
                ...$validated,
                'admin_id' => $user->id,
                'subscription_status' => 'trial',
            ]);

            $user->update(['company_id' => $company->id]);

            return $company;
        });

        return response()->json(new CompanyResource($company), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $company = Company::with(['admin', 'plan'])
            ->withCount('employees')
            ->findOrFail($id);

        abort_unless($request->user()->canAccessCompany($company), 403);

        return response()->json(new CompanyResource($company));
    }

    public function update(UpdateCompanyRequest $request, string $id): JsonResponse
    {
        $company = Company::findOrFail($id);

        abort_unless($request->user()->canManageCompany($company), 403);

        $validated = $request->validated();

        $company->update($validated);

        return response()->json(new CompanyResource($company));
    }
}
