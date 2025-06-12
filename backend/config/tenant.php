<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Tenant Association Sync Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control how user-tenant associations are synchronized
    | between tenant databases and the central mapping table.
    |
    */

    'sync' => [
        /*
        |--------------------------------------------------------------------------
        | Sync Strategy
        |--------------------------------------------------------------------------
        |
        | Available strategies:
        | - 'realtime': Immediate sync via events (low latency, high load)
        | - 'queued': Async sync via queue (higher latency, lower load)
        | - 'batch': Batched sync for high-volume scenarios
        | - 'disabled': No automatic sync (manual only)
        |
        */
        'strategy' => env('TENANT_SYNC_STRATEGY', 'queued'),

        /*
        |--------------------------------------------------------------------------
        | Batch Configuration
        |--------------------------------------------------------------------------
        |
        | Settings for batch sync strategy
        |
        */
        'batch_size' => env('TENANT_SYNC_BATCH_SIZE', 50),
        'batch_delay' => env('TENANT_SYNC_BATCH_DELAY', 30), // seconds
        'batch_timeout' => env('TENANT_SYNC_BATCH_TIMEOUT', 300), // seconds

        /*
        |--------------------------------------------------------------------------
        | Queue Configuration
        |--------------------------------------------------------------------------
        |
        | Settings for queued sync strategy
        |
        */
        'queue_name' => env('TENANT_SYNC_QUEUE', 'tenant-sync'),
        'queue_delay' => env('TENANT_SYNC_QUEUE_DELAY', 0), // seconds
        'max_retries' => env('TENANT_SYNC_MAX_RETRIES', 3),

        /*
        |--------------------------------------------------------------------------
        | Performance Settings
        |--------------------------------------------------------------------------
        |
        | Settings to optimize performance for high-volume scenarios
        |
        */
        'cache_ttl' => env('TENANT_SYNC_CACHE_TTL', 300), // seconds
        'enable_cache' => env('TENANT_SYNC_ENABLE_CACHE', true),
        'chunk_size' => env('TENANT_SYNC_CHUNK_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Logging
    |--------------------------------------------------------------------------
    |
    | Settings for monitoring sync performance and health
    |
    */
    'monitoring' => [
        'log_level' => env('TENANT_SYNC_LOG_LEVEL', 'info'),
        'log_slow_queries' => env('TENANT_SYNC_LOG_SLOW_QUERIES', true),
        'slow_query_threshold' => env('TENANT_SYNC_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
        'enable_metrics' => env('TENANT_SYNC_ENABLE_METRICS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for automatic cleanup of stale associations
    |
    */
    'cleanup' => [
        'enable_auto_cleanup' => env('TENANT_CLEANUP_ENABLED', false),
        'cleanup_schedule' => env('TENANT_CLEANUP_SCHEDULE', 'weekly'),
        'retention_days' => env('TENANT_CLEANUP_RETENTION_DAYS', 90),
    ],
];
