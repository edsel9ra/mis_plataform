<?php

namespace App\Observers;

use App\Models\Employee;
use App\Services\Scheduling\SessionParticipantSyncService;
use Illuminate\Support\Carbon;

class EmployeeObserver
{
    public function __construct(private SessionParticipantSyncService $participantSync) {}

    public function created(Employee $employee): void
    {
        if ($employee->status === 'active') {
            $this->sync($employee, 'added');
        }
    }

    public function updated(Employee $employee): void
    {
        if (! $employee->wasChanged('status')) {
            return;
        }

        $this->sync($employee, $employee->status === 'active' ? 'added' : 'removed');
    }

    public function deleted(Employee $employee): void
    {
        $this->sync($employee, 'removed');
    }

    private function sync(Employee $employee, string $change): void
    {
        $this->participantSync->onMembershipChange(
            $employee->company,
            $employee->user,
            $change,
            Carbon::now(),
        );
    }
}
