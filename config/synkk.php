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

    /*
    |--------------------------------------------------------------------------
    | Subscription Plans & Limitation Matrix
    |--------------------------------------------------------------------------
    |
    | Defines quotas, member limits, storage capacities, and unlocked features
    | for Community Free, Pro Lifetime Deal (LTD), and Synkk Cloud SaaS.
    |
    */
    'plans' => [
        'free' => [
            'name' => 'Community Free',
            'badge' => 'Free CE',
            'max_devices' => env('SYNKK_FREE_MAX_DEVICES', 3),
            'max_vaults' => env('SYNKK_FREE_MAX_VAULTS', 1),
            'max_members' => env('SYNKK_FREE_MAX_MEMBERS', 3),
            'storage_limit_mb' => env('SYNKK_FREE_STORAGE_LIMIT_MB', 1000), // 1 GB
            'features' => [
                'basic_sync',
                'web_editor',
                'interactive_graph',
                'atomic_abort_guard',
            ],
        ],
        'pro_ltd' => [
            'name' => 'Pro Lifetime Deal',
            'badge' => 'Pro LTD',
            'max_devices' => env('SYNKK_PRO_MAX_DEVICES', 25),
            'max_vaults' => env('SYNKK_PRO_MAX_VAULTS', 15),
            'max_members' => env('SYNKK_PRO_MAX_MEMBERS', 10),
            'storage_limit_mb' => env('SYNKK_PRO_STORAGE_LIMIT_MB', 15000), // 15 GB
            'features' => [
                'basic_sync',
                'web_editor',
                'interactive_graph',
                'atomic_abort_guard',
                'path_acls',
                'dlp_scan',
                'remote_wipe',
                'ip_whitelisting',
                'read_only_tokens',
                'plugin_suite_sync',
            ],
        ],
        'cloud' => [
            'name' => 'Synkk Cloud Managed SaaS',
            'badge' => 'Cloud SaaS',
            'max_devices' => env('SYNKK_CLOUD_MAX_DEVICES', 100),
            'max_vaults' => env('SYNKK_CLOUD_MAX_VAULTS', 50),
            'max_members' => env('SYNKK_CLOUD_MAX_MEMBERS', 50),
            'storage_limit_mb' => env('SYNKK_CLOUD_STORAGE_LIMIT_MB', 50000), // 50 GB
            'features' => [
                'basic_sync',
                'web_editor',
                'interactive_graph',
                'atomic_abort_guard',
                'path_acls',
                'dlp_scan',
                'remote_wipe',
                'ip_whitelisting',
                'read_only_tokens',
                'plugin_suite_sync',
                'crdt_multiplayer',
                'e2ee_team',
                'rag_vector_search',
                'cloud_backup',
                'priority_support',
            ],
        ],
    ],
];
