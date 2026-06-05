<?php

return [
    'storage_path' => env('PACKGRID_STORAGE_PATH', 'packgrid'),
    'packages_index' => 'packages.json',
    'packages_prefix' => 'p',

    'docker' => [
        'disk' => env('PACKGRID_DOCKER_DISK', 'local'),
        'storage_path' => env('PACKGRID_DOCKER_STORAGE_PATH', 'docker/blobs'),
        'max_upload_size' => env('PACKGRID_DOCKER_MAX_UPLOAD_SIZE', 10737418240), // 10GB
        'upload_timeout' => (int) env('PACKGRID_DOCKER_UPLOAD_TIMEOUT', 86400), // 24 hours
        'gc_enabled' => env('PACKGRID_DOCKER_GC_ENABLED', true),
        'gc_stale_upload_hours' => env('PACKGRID_DOCKER_GC_STALE_UPLOAD_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registry Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Per-token (or per-IP, for anonymous/fallback access) request limit applied
    | to the Composer, npm, Git and Docker registry routes. Protects the box
    | from scraping/DoS and prevents request floods from exhausting the shared
    | GitHub credential's upstream rate limit. Set to 0 to disable throttling.
    |
    */
    'rate_limit' => [
        'per_minute' => (int) env('PACKGRID_RATE_LIMIT_PER_MINUTE', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Retention
    |--------------------------------------------------------------------------
    |
    | Download and sync logs grow with every request/sync. The packgrid:prune-logs
    | command (scheduled daily) deletes rows older than the configured number of
    | days. Set a value to 0 to keep the corresponding log indefinitely.
    |
    */
    'retention' => [
        'download_logs_days' => (int) env('PACKGRID_DOWNLOAD_LOG_RETENTION_DAYS', 90),
        'sync_logs_days' => (int) env('PACKGRID_SYNC_LOG_RETENTION_DAYS', 90),
    ],
];
