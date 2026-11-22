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
    | Public Pricing Display
    |--------------------------------------------------------------------------
    |
    | Keep the public pricing copy aligned with the currently configured
    | commercial offers. Cloud checkout creates one product per workspace.
    |
    */
    'pricing' => [
        'community' => [
            'amount' => 0,
            'currency_symbol' => '$',
            'billing_label' => 'free forever · full source code',
        ],
        'cloud_pro' => [
            'amount' => 12,
            'currency_symbol' => '$',
            'billing_label' => 'per workspace / month',
        ],
        'cloud_business' => [
            'amount' => 25,
            'currency_symbol' => '$',
            'billing_label' => 'per workspace / month',
        ],
        'pro_ltd' => [
            'amount' => 79,
            'currency_symbol' => '$',
            'billing_label' => 'lifetime deal · pay once, own forever',
        ],
        'ai_addon' => [
            'amount' => (int) env('SYNKK_AI_ADDON_PRICE', 12),
            'currency_symbol' => '$',
            'billing_label' => 'per workspace / month',
        ],
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
                'ai_byok_core',
                'ai_mcp_server',
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
                'rag_vector_search',
                'ai_byok_core',
                'ai_mcp_server',
                'ai_smart_merge',
                'ai_gardener',
                'ai_team_digests',
                'ai_portal_assistant',
            ],
        ],
        'cloud_pro' => [
            'name' => 'Synkk Cloud Pro',
            'badge' => 'Cloud Pro',
            'max_devices' => env('SYNKK_CLOUD_PRO_MAX_DEVICES', 25),
            'max_vaults' => env('SYNKK_CLOUD_PRO_MAX_VAULTS', 5),
            'max_members' => env('SYNKK_CLOUD_PRO_MAX_MEMBERS', 5),
            'storage_limit_mb' => env('SYNKK_CLOUD_PRO_STORAGE_LIMIT_MB', 5000), // 5 GB
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
                'ai_byok_core',
                'ai_mcp_server',
                'ai_smart_merge',
                'ai_gardener',
                'ai_team_digests',
                'ai_portal_assistant',
                'ai_managed_cloud',
            ],
        ],
        'cloud_business' => [
            'name' => 'Synkk Cloud Business',
            'badge' => 'Cloud Business',
            'max_devices' => env('SYNKK_CLOUD_BIZ_MAX_DEVICES', 100),
            'max_vaults' => env('SYNKK_CLOUD_BIZ_MAX_VAULTS', 25),
            'max_members' => env('SYNKK_CLOUD_BIZ_MAX_MEMBERS', 25),
            'storage_limit_mb' => env('SYNKK_CLOUD_BIZ_STORAGE_LIMIT_MB', 25000), // 25 GB
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
                'ai_byok_core',
                'ai_mcp_server',
                'ai_smart_merge',
                'ai_gardener',
                'ai_team_digests',
                'ai_portal_assistant',
                'ai_managed_cloud',
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
                'ai_byok_core',
                'ai_mcp_server',
                'ai_smart_merge',
                'ai_gardener',
                'ai_team_digests',
                'ai_portal_assistant',
                'ai_managed_cloud',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agentic Knowledge Graph & RAG Server
    |--------------------------------------------------------------------------
    |
    | Configuration for local vector embeddings, hybrid semantic search,
    | Ollama / private local LLM endpoints, and Graph-Augmented RAG.
    |
    */
    'rag' => [
        'provider' => env('SYNKK_RAG_PROVIDER', 'deterministic'),
        'ollama_url' => env('SYNKK_RAG_OLLAMA_URL', 'http://localhost:11434'),
        'embedding_model' => env('SYNKK_RAG_EMBEDDING_MODEL', 'nomic-embed-text'),
        'llm_model' => env('SYNKK_RAG_LLM_MODEL', 'llama3.2'),
        'llm_provider' => env('SYNKK_RAG_LLM_PROVIDER', 'local-first'),
        'openai_api_key' => env('SYNKK_RAG_OPENAI_API_KEY', env('OPENAI_API_KEY')),
        'openai_model' => env('SYNKK_RAG_OPENAI_MODEL', 'gpt-4o-mini'),
        'dimensions' => 128,
    ],

    /*
    |--------------------------------------------------------------------------
    | Opt-In AI Add-On (Managed Cloud Inference & Credit Allowances)
    |--------------------------------------------------------------------------
    |
    | Settings for Synkk Managed Cloud AI, trial/monthly credit allowances,
    | and per-1M token microcent pricing estimates for quota accounting.
    |
    */
    'ai' => [
        'managed' => [
            'provider' => env('SYNKK_AI_MANAGED_PROVIDER', 'openai_compatible'),
            'base_url' => env('SYNKK_AI_MANAGED_BASE_URL', 'https://openrouter.ai/api/v1'),
            'api_key' => env('SYNKK_AI_MANAGED_API_KEY'),
            'chat_model' => env('SYNKK_AI_MANAGED_CHAT_MODEL', 'openai/gpt-4o-mini'),
            'fast_model' => env('SYNKK_AI_MANAGED_FAST_MODEL', 'openai/gpt-4o-mini'),
            'embedding_provider' => env('SYNKK_AI_MANAGED_EMBEDDING_PROVIDER', 'openai_compatible'),
            'embedding_base_url' => env('SYNKK_AI_MANAGED_EMBEDDING_BASE_URL', 'https://api.openai.com/v1'),
            'embedding_api_key' => env('SYNKK_AI_MANAGED_EMBEDDING_API_KEY', env('SYNKK_AI_MANAGED_API_KEY')),
            'embedding_model' => env('SYNKK_AI_MANAGED_EMBEDDING_MODEL', 'text-embedding-3-small'),
        ],
        'credits' => [
            'trial_microcents' => (int) env('SYNKK_AI_TRIAL_MICROCENTS', 200_000_000),
            'monthly_microcents' => (int) env('SYNKK_AI_MONTHLY_MICROCENTS', 1_200_000_000),
        ],
        'pricing_microcents_per_1m' => [
            'default_input' => 15_000_000,
            'default_output' => 60_000_000,
            'default_embedding' => 2_000_000,
            'models' => [
                'gpt-4o-mini' => ['input' => 15_000_000, 'output' => 60_000_000],
                'openai/gpt-4o-mini' => ['input' => 15_000_000, 'output' => 60_000_000],
                'gpt-4o' => ['input' => 250_000_000, 'output' => 1_000_000_000],
                'openai/gpt-4o' => ['input' => 250_000_000, 'output' => 1_000_000_000],
                'claude-3-5-sonnet' => ['input' => 300_000_000, 'output' => 1_500_000_000],
                'claude-3-5-sonnet-latest' => ['input' => 300_000_000, 'output' => 1_500_000_000],
                'anthropic/claude-3.5-sonnet' => ['input' => 300_000_000, 'output' => 1_500_000_000],
                'gemini-2.0-flash' => ['input' => 10_000_000, 'output' => 40_000_000],
                'google/gemini-2.0-flash-001' => ['input' => 10_000_000, 'output' => 40_000_000],
                'text-embedding-3-small' => ['input' => 2_000_000, 'output' => 0],
            ],
        ],
    ],
];
