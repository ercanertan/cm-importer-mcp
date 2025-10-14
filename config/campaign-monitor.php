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

    // Batch size for processing CSV rows (default: 100)
    'batch_size' => env('CM_BATCH_SIZE', 100),

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