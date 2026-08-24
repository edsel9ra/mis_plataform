<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncPendingMatchScores extends Command
{
    protected $signature = 'app:sync-pending-match-scores';
    protected $description = 'Safely report that pending match score sync is unavailable';

    public function handle(): int
    {
        $this->info('No-op: matching score persistence is not implemented; no external request was made.');

        return self::SUCCESS;
    }
}
