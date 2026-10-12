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
];
