<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Models\ObjectiveEvidence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

    public function handle(): int
    {
        $hours = max(0, (int) $this->option('hours'));
        $evidenceMorph = (new ObjectiveEvidence)->getMorphClass();

        $orphans = MediaAsset::query()
            ->where('purpose', 'objective_evidence')
            ->where('created_at', '<', now()->subHours($hours))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('objective_evidences')
                    ->whereColumn('objective_evidences.media_asset_id', 'media_assets.id');
            })
            ->where(function ($query) use ($evidenceMorph) {
                $query->where('mediaable_type', '!=', $evidenceMorph)
                    ->orWhereNull('mediaable_type')
                    ->orWhereNotExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('objective_evidences')
                            ->whereColumn('objective_evidences.id', 'media_assets.mediaable_id');
                    });
            });

        if ($this->option('dry-run')) {
            $count = $orphans->count();
            $this->info("[dry-run] {$count} orphan evidence media assets would be purged.");
            Log::info('media:purge-orphan-evidence dry run', ['count' => $count]);

            return self::SUCCESS;
        }

        $purged = [];
        $failed = [];
        $orphans->chunkById(500, function ($assets) use (&$purged, &$failed) {
            foreach ($assets as $asset) {
                $disk = Storage::disk($asset->disk);
                // A file already gone counts as purged; a failed deletion
                // keeps the row so the next run can retry it.
                if ($disk->delete($asset->path) || ! $disk->exists($asset->path)) {
                    $asset->delete();
                    $purged[] = $asset->id;
                } else {
                    $failed[] = $asset->id;
                }
            }
        });

        $this->info(sprintf('Purged %d orphan evidence media assets.', count($purged)));
        Log::info('media:purge-orphan-evidence purged assets', [
            'count' => count($purged),
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
