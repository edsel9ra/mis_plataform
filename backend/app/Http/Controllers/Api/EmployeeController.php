<?php

namespace App\Http\Controllers\Api;

use App\Actions\InviteEmployeeAction;
use App\Http\Requests\Employee\InviteEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController
{
    public function __construct(
        private InviteEmployeeAction $inviteEmployee,
    ) {}

    public function index(Request $request, string $companyId): JsonResponse
    {
        abort_unless($request->user()->canManageCompany($companyId), 403);

        $query = Employee::where('company_id', $companyId);
        $statusCounts = (clone $query)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $employees = $query
            ->with('user')
            ->paginate(20);

        return EmployeeResource::collection($employees)
            ->additional([
                'status_counts' => [
                    'active' => (int) ($statusCounts['active'] ?? 0),
                    'invited' => (int) ($statusCounts['invited'] ?? 0),
                    'inactive' => (int) ($statusCounts['inactive'] ?? 0),
                ],
            ])
            ->response();
    }

    public function invite(InviteEmployeeRequest $request, string $companyId): JsonResponse
    {
        $company = Company::findOrFail($companyId);

        abort_unless($request->user()->canManageCompany($company), 403);

        $employee = $this->inviteEmployee->execute($company, $request->validated());

        return response()->json([
            'employee' => new EmployeeResource($employee->load('user')),
        ], 201);
    }

    public function update(UpdateEmployeeRequest $request, string $id): JsonResponse
    {
        $employee = Employee::with('company')->findOrFail($id);

        abort_unless($request->user()->canManageCompany($employee->company), 403);

        $validated = $request->validated();

        $employee->update($validated);

        return response()->json(new EmployeeResource($employee->load('user')));
    }

    public function activate(Request $request, string $id): JsonResponse
    {
        $employee = Employee::with('company')->findOrFail($id);

        abort_unless($request->user()->canManageCompany($employee->company), 403);

        $employee->update([
            'status' => 'active',
            'activated_at' => now(),
        ]);

        $employee->user->update(['is_active' => true]);

        return response()->json(new EmployeeResource($employee->load('user')));
    }

    public function deactivate(Request $request, string $id): JsonResponse
    {
        $employee = Employee::with('company')->findOrFail($id);

        abort_unless($request->user()->canManageCompany($employee->company), 403);

        $employee->update(['status' => 'inactive']);

        $employee->user->update(['is_active' => false]);

        return response()->json(new EmployeeResource($employee->load('user')));
    }
}
