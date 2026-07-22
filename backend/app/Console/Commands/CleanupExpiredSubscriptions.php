<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class CleanupExpiredSubscriptions extends Command
{
    protected $signature = 'app:cleanup-expired-subscriptions';
    protected $description = 'Mark expired subscriptions as expired';

    public function handle(): void
    {
        $expired = Subscription::whereIn('status', ['trial', 'active'])
            ->where('ends_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$expired} subscriptions");
    }
}
