<?php

return [
    'app_id' => env('REVERB_APP_ID'),
    'app_key' => env('REVERB_APP_KEY'),
    'app_secret' => env('REVERB_APP_SECRET'),
    'host' => env('REVERB_HOST', 'localhost'),
    'port' => env('REVERB_PORT', 8080),
    'scheme' => env('REVERB_SCHEME', 'http'),
    'options' => [
        'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
        'port' => env('REVERB_SERVER_PORT', 8080),
        'hostname' => env('REVERB_SERVER_HOSTNAME', '0.0.0.0'),
        'debug' => env('REVERB_DEBUG', false),
    ],
    'scaling' => [
        'enabled' => env('REVERB_SCALING_ENABLED', false),
        'channel' => env('REVERB_SCALING_CHANNEL', 'reverb_scaling'),
        'server' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'database' => env('REDIS_DB', '0'),
        ],
    ],
    'pong_timeout' => env('REVERB_PONG_TIMEOUT', 60),
    'ping_interval' => env('REVERB_PING_INTERVAL', 25),
    'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10000),
];
