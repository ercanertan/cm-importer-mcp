<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Campaign Monitor Import Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for the Campaign Monitor
    | import system. These settings control various aspects of CSV importing,
    | custom field handling, logging, and queue processing.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | File Upload Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for CSV file uploads and processing limits.
    |
    */

    // Maximum file upload size in KB (default: 10MB)
    'max_upload_size' => env('CM_MAX_UPLOAD_SIZE', 10240),

    // Batch size for processing CSV rows (larger = faster but more memory)
    'batch_size' => env('CM_BATCH_SIZE', 2000),

    // PDO bulk insert threshold - use PDO for better performance (lower = faster)
    'pdo_threshold' => env('CM_PDO_THRESHOLD', 50),

    // PDO chunk size - MySQL can handle ~8000 rows with 8 columns
    'pdo_chunk_size' => env('CM_PDO_CHUNK_SIZE', 2000),

    // Queue chunk size - number of records per queue job (for chunked processing)
    'queue_chunk_size' => env('CM_QUEUE_CHUNK_SIZE', 5000),

    /*
    |--------------------------------------------------------------------------
    | Data Processing Settings
    |--------------------------------------------------------------------------
    |
    | Settings that control how data is processed and stored.
    |
    */

    // Auto-detect data types for custom fields
    'auto_detect_data_types' => env('CM_AUTO_DETECT_DATA_TYPES', true),

    // Default status for imported users
    'default_status' => env('CM_DEFAULT_STATUS', 'active'),

    // Enable file type selection in UI (active, bounced, deleted, unsubscribed)
    // When disabled, all imports default to 'active' status
    'enable_file_type_selection' => env('CM_ENABLE_FILE_TYPE_SELECTION', false),

    /*
    |--------------------------------------------------------------------------
    | Logging Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for import logging and error tracking.
    |
    */

    // Enable detailed import logging
    'import_logging' => env('CM_IMPORT_LOGGING', true),

    // Log channel to use for Campaign Monitor imports
    'log_channel' => env('CM_LOG_CHANNEL', 'stack'),

    // Log individual failed rows for debugging
    'log_failed_rows' => env('CM_LOG_FAILED_ROWS', true),

    /*
    |--------------------------------------------------------------------------
    | Queue Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for background job processing.
    |
    */

    // Enable queue processing for large imports
    'queue_enabled' => env('CM_QUEUE_ENABLED', false),

    // Queue connection to use
    'queue_connection' => env('CM_QUEUE_CONNECTION', 'database'),

    // Queue name for import jobs
    'queue_name' => env('CM_QUEUE_NAME', 'imports'),

    /*
    |--------------------------------------------------------------------------
    | Custom Field Settings
    |--------------------------------------------------------------------------
    |
    | Settings for handling Campaign Monitor custom fields.
    |
    */

    // Standard fields that should not be treated as custom fields
    'standard_fields' => [
        'email',
        'name',
        'cm_subscriber_id',
        'cm_status',
        'cm_subscribed_at',
        'cm_unsubscribed_at',
    ],

    // Data type detection patterns
    'data_type_patterns' => [
        'number' => '/^[0-9]+(\.[0-9]+)?$/',
        'date' => '/^\d{4}-\d{2}-\d{2}/',
        'multi_select' => '/[,;]/',
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    |
    | Settings for the web interface.
    |
    */

    // Number of recent imports to show in the UI
    'recent_imports_limit' => 10,

    // Number of preview rows to show
    'preview_rows_limit' => 5,

    /*
    |--------------------------------------------------------------------------
    | Validation Settings
    |--------------------------------------------------------------------------
    |
    | Settings for data validation during import.
    |
    */

    // Require email field in CSV
    'require_email_field' => true,

    // Allowed CSV file extensions
    'allowed_file_extensions' => ['csv', 'txt'],

    // Maximum number of custom fields per import
    'max_custom_fields' => 50,

    /*
    |--------------------------------------------------------------------------
    | Campaign Monitor API Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Campaign Monitor API integration.
    |
    */

    // Campaign Monitor API Key
    'api_key' => env('CM_API_KEY'),

    // Campaign Monitor Client ID
    'client_id' => env('CM_CLIENT_ID'),

    // Campaign Monitor List ID (single list for all subscribers)
    'list_id' => env('CM_LIST_ID'),

    // Core custom fields synced to Campaign Monitor (4 fields only)
    'core_fields' => [
        'user_id' => [
            'key' => 'user_id',
            'name' => 'Laravel User ID',
            'data_type' => 'Text',
            'visible_in_preference_center' => false,
            'description' => 'Internal Laravel user ID for reliable webhook processing',
        ],
        'organization_name' => [
            'key' => 'organization_name',
            'name' => 'Organization Name',
            'data_type' => 'Text',
            'visible_in_preference_center' => true,
            'description' => 'User\'s organization name for personalization',
        ],
        'tier' => [
            'key' => 'tier',
            'name' => 'Subscription Tier',
            'data_type' => 'Text',
            'visible_in_preference_center' => false,
            'description' => 'Subscription tier (free, paid_pro, paid_premium, enterprise)',
        ],
        'temp_campaign_tag' => [
            'key' => 'temp_campaign_tag',
            'name' => 'Campaign Tag (Auto-Managed)',
            'data_type' => 'Text',
            'visible_in_preference_center' => false,
            'description' => 'Temporary tag for complex CDP-driven campaigns',
        ],
    ],

    // Segment naming prefixes for clarity in CM
    'segment_prefixes' => [
        'recurring' => '[Recurring]',
        'one_off' => '[One-off]',
    ],

    // Days to keep one-off segments before cleanup
    'segment_cleanup_days' => env('CM_SEGMENT_CLEANUP_DAYS', 30),

    // Bulk tagging settings
    'bulk_import_batch_size' => env('CM_BULK_IMPORT_BATCH_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Auto-Sync Settings
    |--------------------------------------------------------------------------
    |
    | Settings for automatic syncing via UserObserver.
    |
    */

    // Disable automatic sync to Campaign Monitor via UserObserver
    'disable_auto_sync' => env('CM_DISABLE_AUTO_SYNC', false),

    // Threshold for immediate vs queued sync (organization tier changes)
    // If organization has <= this many users, sync immediately
    // If > this many users, queue bulk sync job
    'sync_threshold' => env('CM_SYNC_THRESHOLD', 10),

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Settings that affect import performance.
    |
    */

    // Use database transactions for batch processing
    'use_transactions' => true,

    // Job timeout in seconds (for queue processing)
    'job_timeout' => 3600,

    // Memory limit for large imports (in MB)
    'memory_limit' => 512,

];