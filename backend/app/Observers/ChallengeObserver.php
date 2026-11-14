<?php

namespace App\Observers;

use App\Models\Challenge;
use App\Services\Progress\ModuleCompletionService;
use Illuminate\Support\Facades\DB;

class ChallengeObserver
{
    public function __construct(
        private ModuleCompletionService $moduleCompletion,
    ) {}

    public function deleted(Challenge $challenge): void
    {
        $event = $challenge->isForceDeleting() ? 'challenge.force_deleted' : 'challenge.deleted';
        $this->recalculate($challenge, $event);
    }

    public function forceDeleted(Challenge $challenge): void
    {
        $this->recalculate($challenge, 'challenge.force_deleted');
    }

    public function restored(Challenge $challenge): void
    {
        $this->recalculate($challenge, 'challenge.restored');
    }

    /**
     * Deleting or restoring a linked challenge changes its module's
     * eligible set, so the completion row is recalculated in the same
     * transaction as the write (savepoint when already inside one).
     */
    private function recalculate(Challenge $challenge, string $event): void
    {
        if ($challenge->path_module_id === null || $challenge->mentee_id === null) {
            return;
        }

        DB::transaction(fn () => $this->moduleCompletion->recalculate(
            $challenge->mentee_id,
            $challenge->path_module_id,
            auth()->user(),
            $event,
            $challenge->id,
            now(),
        ));
    }
}
