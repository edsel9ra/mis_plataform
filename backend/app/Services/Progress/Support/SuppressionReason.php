<?php

namespace App\Services\Progress\Support;

/**
 * Privacy suppression reason codes (Spec 001 aggregate rule + Spec 013
 * per-metric contribution threshold). Codes only — never counts.
 */
enum SuppressionReason: string
{
    /** Eligible base below the shared threshold of 3. */
    case InsufficientBase = 'insufficient_base';

    /** Fewer than 3 calculable contributions for that metric. */
    case InsufficientMetricContributions = 'insufficient_metric_contributions';
}
