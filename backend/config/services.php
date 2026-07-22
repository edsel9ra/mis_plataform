<?php

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('APP_URL') . '/auth/google/callback',
        'calendar' => [
            'impersonate' => env('GOOGLE_CALENDAR_IMPERSONATE'),
        ],
    ],
    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('APP_URL') . '/auth/linkedin/callback',
    ],
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('APP_URL') . '/auth/github/callback',
    ],
    'matching' => [
        'url' => env('MATCHING_SERVICE_URL', 'http://matching:8000'),
        'timeout' => 30,
    ],
    'ipfs' => [
        'api_url' => env('IPFS_API_URL', 'https://api.pinata.cloud'),
        'api_key' => env('IPFS_API_KEY'),
        'secret_key' => env('IPFS_SECRET_API_KEY'),
    ],
    'polygon' => [
        'rpc_url' => env('POLYGON_RPC_URL'),
        'private_key' => env('POLYGON_PRIVATE_KEY'),
        'contract_address' => env('POLYGON_CONTRACT_ADDRESS'),
    ],
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],
    'mercadopago' => [
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
    ],
];
