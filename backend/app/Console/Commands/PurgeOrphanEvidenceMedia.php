<?php

namespace App\Console\Commands;

use App\Actions\PurgeOrphanEvidenceMediaAssetAction;
use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily purge of objective-evidence media orphans (RF-020.10): assets whose
 * upload was never attached to an evidence, or whose evidence row disappeared
 * through a cascade, older than the grace window. Deletes the file first and
 * then the row. Logged data is limited to asset ids and counts — never file
 * names, notes or user data.
 */
class PurgeOrphanEvidenceMedia extends Command
{
    protected $signature = 'media:purge-orphan-evidence {--hours=24} {--dry-run}';

    protected $description = 'Delete objective evidence media not linked to any evidence past the grace window';

    public function handle(PurgeOrphanEvidenceMediaAssetAction $purgeAsset): int
    {
        $hours = max(0, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);

        $orphans = MediaAsset::query()->orphanEvidence($cutoff);

        if ($this->option('dry-run')) {
            $count = $orphans->count();
            $this->info("[dry-run] {$count} orphan evidence media assets would be purged.");
            Log::info('media:purge-orphan-evidence dry run', ['count' => $count]);

            return self::SUCCESS;
        }

        $purged = [];
        $failed = [];
        $skipped = 0;
        $orphans->chunkById(500, function ($assets) use (&$purged, &$failed, &$skipped, $purgeAsset, $cutoff) {
            foreach ($assets as $asset) {
                $result = $purgeAsset->execute($asset->id, $cutoff);
                if ($result === PurgeOrphanEvidenceMediaAssetAction::PURGED) {
                    $purged[] = $asset->id;
                } elseif ($result === PurgeOrphanEvidenceMediaAssetAction::FAILED) {
                    $failed[] = $asset->id;
                } else {
                    $skipped++;
                }
            }
        });

        $this->info(sprintf(
            'Purged %d orphan evidence media assets; %d skipped.',
            count($purged),
            $skipped,
        ));
        Log::info('media:purge-orphan-evidence purged assets', [
            'count' => count($purged),
            'skipped' => $skipped,
            'asset_ids' => $purged,
        ]);

        if ($failed !== []) {
            $this->warn(sprintf('%d orphan evidence media assets could not be deleted.', count($failed)));
            Log::warning('media:purge-orphan-evidence failed deletions', [
                'count' => count($failed),
                'asset_ids' => $failed,
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
