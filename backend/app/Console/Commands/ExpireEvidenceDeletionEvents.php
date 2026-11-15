<?php

namespace App\Console\Commands;

use App\Models\EvidenceDeletionEvent;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Purge minimal deletion events once their retention window closes
 * (docs/politica-retencion.md §5.2 and the §7 exception, plan §9). This is
 * the only purge the policy enables for the minimal register: the actor
 * correspondence rows disappear through the FK cascade, and every run —
 * including zero-expiry runs — is recorded on the progress channel with
 * just a count and the instant, never event ids, pseudonyms, subjects or
 * actors. Runs regardless of deletion_event_retention_months: that setting
 * gates creation only; already-written events expire on their own
 * expires_at.
 */
class ExpireEvidenceDeletionEvents extends Command
{
    protected $signature = 'app:expire-evidence-deletion-events';

    protected $description = 'Delete evidence deletion events past their retention expiry';

    public function handle(): int
    {
        $now = CarbonImmutable::now();

        $expired = EvidenceDeletionEvent::query()
            ->where('expires_at', '<=', $now)
            ->delete();

        activity('progress')
            ->event('competency.evidence_deletion_events_expired')
            ->withProperties([
                'expired_count' => $expired,
                'occurred_at' => $now->toIso8601String(),
            ])
            ->log('competency.evidence_deletion_events_expired');

        $this->info("Expired {$expired} evidence deletion events");

        return self::SUCCESS;
    }
}
