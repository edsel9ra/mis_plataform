<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Collective privacy threshold
    |--------------------------------------------------------------------------
    | Minimum number of eligible participants required before a collective
    | aggregate is shown. Applied uniformly to members, managers and mentors.
    */
    'collective_privacy_threshold' => (int) env('PROGRESS_COLLECTIVE_PRIVACY_THRESHOLD', 3),

    /*
    |--------------------------------------------------------------------------
    | Upcoming commitments window
    |--------------------------------------------------------------------------
    | Number of days ahead used to build the "next commitments" indicator.
    */
    'commitments_window_days' => (int) env('PROGRESS_COMMITMENTS_WINDOW_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Panel cache TTL
    |--------------------------------------------------------------------------
    | Redis caches the computed payload for this many seconds. Redis is never
    | the source of truth (P1); the cache is invalidated by model observers.
    */
    'cache_ttl' => (int) env('PROGRESS_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Mentor portfolio inactivity window
    |--------------------------------------------------------------------------
    | A mentee is flagged as "no activity" when there is no objective, session
    | or challenge movement within this many days (RF-9 CA-9.4).
    */
    'inactivity_days' => (int) env('PROGRESS_INACTIVITY_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Deletion-event retention
    |--------------------------------------------------------------------------
    | Months a minimal evidence-deletion event is retained before purge
    | (docs/politica-retencion.md §5.1/§5.3). Disabled values normalize to
    | null in EvidenceDeletionEventCalculator::retentionMonths.
    */
    'deletion_event_retention_months' => env('PROGRESS_DELETION_EVENT_RETENTION_MONTHS', 12),
];
