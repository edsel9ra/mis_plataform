<?php

namespace App\Observers;

use App\Models\CohortMember;
use App\Services\Scheduling\SessionParticipantSyncService;
use Illuminate\Support\Carbon;

class CohortMemberObserver
{
    public function __construct(private SessionParticipantSyncService $participantSync) {}

    public function created(CohortMember $member): void
    {
        if ($member->status === 'active') {
            $this->sync($member, 'added');
        }
    }

    public function updated(CohortMember $member): void
    {
        if (! $member->wasChanged('status')) {
            return;
        }

        $this->sync($member, $member->status === 'active' ? 'added' : 'removed');
    }

    public function deleted(CohortMember $member): void
    {
        $this->sync($member, 'removed');
    }

    private function sync(CohortMember $member, string $change): void
    {
        $this->participantSync->onMembershipChange(
            $member->cohort,
            $member->user,
            $change,
            Carbon::now(),
        );
    }
}
