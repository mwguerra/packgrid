<?php

return [
    'storage_path' => env('PACKGRID_STORAGE_PATH', 'packgrid'),
    'packages_index' => 'packages.json',
    'packages_prefix' => 'p',

    'docker' => [
        'disk' => env('PACKGRID_DOCKER_DISK', 'local'),
        'storage_path' => env('PACKGRID_DOCKER_STORAGE_PATH', 'docker/blobs'),
        'max_upload_size' => env('PACKGRID_DOCKER_MAX_UPLOAD_SIZE', 10737418240), // 10GB
        'upload_timeout' => env('PACKGRID_DOCKER_UPLOAD_TIMEOUT', 86400), // 24 hours
        'gc_enabled' => env('PACKGRID_DOCKER_GC_ENABLED', true),
        'gc_stale_upload_hours' => env('PACKGRID_DOCKER_GC_STALE_UPLOAD_HOURS', 24),
    ],
];
