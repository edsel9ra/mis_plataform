<?php

return [
    'driver' => env('SCOUT_DRIVER', 'meilisearch'),
    'prefix' => env('SCOUT_PREFIX', ''),
    'queue' => env('SCOUT_QUEUE', true),
    'after_commit' => false,
    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://meilisearch:7700'),
        'key' => env('MEILISEARCH_KEY', null),
    ],
];
