<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class CleanupExpiredSubscriptions extends Command
{
    protected $signature = 'app:cleanup-expired-subscriptions';
    protected $description = 'Mark expired subscriptions as expired';

    public function handle(): int
    {
        $now = now();
        $expiredTrials = Subscription::query()
            ->where('status', 'trial')
            ->where(function ($query) use ($now) {
                $query->where('trial_ends_at', '<=', $now)
                    ->orWhere(function ($query) use ($now) {
                        $query->whereNull('trial_ends_at')
                            ->whereNotNull('ends_at')
                            ->where('ends_at', '<=', $now);
                    });
            })
            ->update(['status' => 'expired']);
        $expiredActive = Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->update(['status' => 'expired']);
        $expired = $expiredTrials + $expiredActive;

        $this->info("Expired {$expired} subscriptions");

        return self::SUCCESS;
    }
}
