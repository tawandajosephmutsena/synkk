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

    /*
    |--------------------------------------------------------------------------
    | LemonSqueezy Commercial Store & Licensing
    |--------------------------------------------------------------------------
    |
    | License verification parameters for $49 Lifetime License activation.
    |
    */
    'lemon_squeezy' => [
        'store_url' => env('LEMON_SQUEEZY_STORE_URL', ''),
        'store_id' => env('LEMON_SQUEEZY_STORE_ID', ''),
        'product_id' => env('LEMON_SQUEEZY_PRODUCT_ID', ''),
        'api_url' => 'https://api.lemonsqueezy.com/v1/licenses/activate',
        'enforce_license' => env('SYNKK_ENFORCE_LICENSE', false),
    ],
];
