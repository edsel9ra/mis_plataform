<?php

return [
    'supported_client_types' => ['personal'],

    'media' => [
        'max_files' => 5,
        'max_size_kb' => 25600,
        'mimetypes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
            'text/plain',
            'text/csv',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'audio/mpeg',
            'audio/wav',
            'audio/x-wav',
            'audio/mp4',
            'audio/webm',
            'video/mp4',
            'video/quicktime',
            'video/webm',
        ],
    ],

    'alerts' => [
        'stagnation_days' => 7,
        'disengagement_days' => 7,
    ],
];
