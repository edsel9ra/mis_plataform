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
            // Lock first, then re-check in a separate statement: under READ
            // COMMITTED a FOR UPDATE that waited on a concurrent attach
            // re-evaluates its subqueries against the old snapshot, so the
            // orphan check must run after the lock with a fresh one.
            $asset = MediaAsset::whereKey($assetId)->lockForUpdate()->first();

            if ($asset === null || ! MediaAsset::whereKey($assetId)->orphanEvidence($cutoff)->exists()) {
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
