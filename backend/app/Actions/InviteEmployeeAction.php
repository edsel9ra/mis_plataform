<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\ContentAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteEmployeeAction
{
    public function __construct(
        private ContentAccessService $contentAccess,
    ) {}

    public function execute(Company $company, array $data): Employee
    {
        return DB::transaction(function () use ($company, $data) {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);
            $plan = $this->contentAccess->activePlanForSource('company', $company->id, 'empresa');

            if ($plan && $company->employees()->count() >= $plan->max_members) {
                throw ValidationException::withMessages([
                    'employee' => [__('companies.employee_limit_reached')],
                ]);
            }

            $tempPassword = Str::password(32);

            $user = User::create([
                'name' => $data['name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => mb_strtolower(trim((string) $data['email'])),
                'password' => Hash::make($tempPassword),
                'client_type' => 'empresa',
                'role' => UserRole::Employee->value,
                'company_id' => $company->id,
                'is_active' => false,
            ]);
            $user->syncApplicationRole(UserRole::Employee->value);

            $employee = Employee::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'position' => $data['position'] ?? null,
                'department' => $data['department'] ?? null,
                'status' => 'invited',
                'invited_at' => now(),
            ]);

            Password::sendResetLink(['email' => $user->email]);

            return $employee;
        });
    }
}
