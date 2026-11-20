<?php

namespace App\Actions;

use App\Models\MediaAsset;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Purge a single orphan evidence media asset (RF-020.10). The orphan
 * conditions are re-checked under a row lock inside a transaction so an
 * asset attached to an evidence after the candidate selection is skipped
 * instead of deleted.
 */
class PurgeOrphanEvidenceMediaAssetAction
{
    public const PURGED = 'purged';

    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    public function execute(string $assetId, CarbonInterface $cutoff): string
    {
        return DB::transaction(function () use ($assetId, $cutoff) {
            $asset = MediaAsset::whereKey($assetId)
                ->orphanEvidence($cutoff)
                ->lockForUpdate()
                ->first();

            if ($asset === null) {
                return self::SKIPPED;
            }

            $disk = Storage::disk($asset->disk);
            // A file already gone counts as purged; a failed deletion keeps
            // the row so the next run can retry it.
            if (! $disk->delete($asset->path) && $disk->exists($asset->path)) {
                return self::FAILED;
            }

            $asset->delete();

            return self::PURGED;
        });
    }
}
