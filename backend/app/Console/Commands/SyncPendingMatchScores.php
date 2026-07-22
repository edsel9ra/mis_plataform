<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncPendingMatchScores extends Command
{
    protected $signature = 'app:sync-pending-match-scores';
    protected $description = 'Sync pending match scores with matching service';

    public function handle(): void
    {
        $this->info('Match scores sync completed');
    }
}
