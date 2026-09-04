<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Synkk Vault Storage Disk
    |--------------------------------------------------------------------------
    |
    | Disk configured in config/filesystems.php where vault files and version
    | snapshots will be stored. Defaults to 'local'.
    |
    */
    'storage_disk' => env('SYNKK_STORAGE_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Batch Size
    |--------------------------------------------------------------------------
    |
    | Maximum number of file uploads/deletes allowed per batch-sync request.
    |
    */
    'max_batch_size' => env('SYNKK_MAX_BATCH_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | Version Retention Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of historical versions to keep per file.
    |
    */
    'version_retention_limit' => env('SYNKK_VERSION_RETENTION_LIMIT', 25),
];
