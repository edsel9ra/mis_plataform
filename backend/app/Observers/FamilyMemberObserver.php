<?php

namespace App\Observers;

use App\Models\FamilyMember;
use App\Services\Scheduling\SessionParticipantSyncService;
use Illuminate\Support\Carbon;

class FamilyMemberObserver
{
    public function __construct(private SessionParticipantSyncService $participantSync) {}

    public function created(FamilyMember $member): void
    {
        $this->sync($member, 'added');
    }

    public function deleted(FamilyMember $member): void
    {
        $this->sync($member, 'removed');
    }

    private function sync(FamilyMember $member, string $change): void
    {
        if ($member->user_id === null) {
            return;
        }

        $this->participantSync->onMembershipChange(
            $member->familyGroup,
            $member->user,
            $change,
            Carbon::now(),
        );
    }
}
