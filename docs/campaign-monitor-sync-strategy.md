# Campaign Monitor Sync Strategy

## Overview

This document explains how the Campaign Monitor integration handles syncing users between Laravel and Campaign Monitor, with special consideration for bulk operations.

## The Challenge: Bulk Imports vs Individual Syncs

### Problem

The system supports two types of user operations:

1. **Individual Operations** (should sync immediately)
   - Admin creates/updates a single user
   - User updates their own profile
   - Organization admin adds a team member

2. **Bulk Operations** (should NOT sync individually)
   - CSV import of 10,000+ users
   - Organization bulk import
   - Backfill operations

If the UserObserver synced every user during a bulk import:
- 10,000 users = 10,000 individual API calls to Campaign Monitor
- Extremely slow (hours to complete)
- Hit Campaign Monitor rate limits
- Potential API failures
- Wasted resources

## Solution: Smart Observer with Bulk Detection

### Architecture

```
┌─────────────────────────────────────────────────┐
│          User Operation Initiated               │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│          UserObserver Triggered                  │
│  1. Check if bulk operation flag is set         │
│  2. Check if running in console (import cmd)    │
│  3. Check if auto-sync is disabled              │
└─────────────────────────────────────────────────┘
        ↓                           ↓
    [Bulk Mode]               [Normal Mode]
        ↓                           ↓
   Skip sync                 Sync to CM immediately
        ↓                           ↓
   Continue                   Log result
                                   ↓
                            Operation complete
```

### Implementation Details

#### 1. Bulk Operation Detection

The `UserObserver` checks three conditions:

**A. Bulk Import Flag**
```php
// Set in CampaignMonitorImportService
app()->instance('cm.bulk_import_active', true);

// Checked in UserObserver
if (app()->has('cm.bulk_import_active')) {
    return app('cm.bulk_import_active') === true;
}
```

**B. Console Command Detection**
```php
if (app()->runningInConsole()) {
    // Skip sync for import commands
    $importCommands = [
        'cm:import',
        'import:csv',
        'import:users',
        'cm:backfill',
    ];

    // Allow sync for specific commands
    $allowedCommands = ['tinker'];
}
```

**C. Global Disable Flag**
```php
// Set in .env
CM_DISABLE_AUTO_SYNC=true

// Or programmatically
config(['campaign-monitor.disable_auto_sync' => true]);
```

#### 2. Import Lifecycle

**During CSV Import:**

```php
public function processChunkData($chunkData, $headers, $log)
{
    try {
        // 1. Set bulk import flag
        app()->instance('cm.bulk_import_active', true);

        // 2. Process thousands of users
        $this->processBatchWithPDO($chunkData);
        // UserObserver skips each user

        // 3. Return results
        return ['success' => true, ...];

    } finally {
        // 4. Clear flag when done
        app()->forgetInstance('cm.bulk_import_active');
    }
}
```

**After Import Completes:**

Option A: Queue a bulk sync job
```php
// After import finishes
BulkSyncToCampaignMonitor::dispatch($importLogId);
```

Option B: Let admin trigger sync manually
```php
php artisan cm:backfill-core-fields --import-log=123
```

#### 3. Smart Field Change Detection

The UserObserver only syncs when CM-relevant fields change:

```php
protected function hasCmRelevantChanges(User $user): bool
{
    $cmFields = [
        'email',           // Standard field
        'fullname',        // Standard field
        'tier',            // Custom field
        'organization_id', // Affects organization_name
        'permission_to_track', // Standard field
    ];

    foreach ($cmFields as $field) {
        if ($user->wasChanged($field)) {
            return true;
        }
    }

    return false;
}
```

And only syncs the changed fields:

```php
protected function getChangedCmFields(User $user): array
{
    $changedFields = [];

    $fieldMap = [
        'tier' => 'tier',
        'organization_id' => 'organization_name',
    ];

    foreach ($fieldMap as $laravelField => $cmField) {
        if ($user->wasChanged($laravelField)) {
            $changedFields[] = $cmField;
        }
    }

    return $changedFields; // e.g., ['tier', 'organization_name']
}
```

## Usage Examples

### Example 1: CSV Import (Bulk Mode)

```php
// Admin uploads CSV with 15,000 users
// System processes in chunks

// CampaignMonitorImportService sets flag
app()->instance('cm.bulk_import_active', true);

// Each user is created
User::create([...]) // Observer skips sync ✓

// After import completes, flag cleared
app()->forgetInstance('cm.bulk_import_active');

// Admin runs backfill command
php artisan cm:backfill-core-fields --recent
// Syncs all users from the import in batches
```

### Example 2: Single User Creation (Normal Mode)

```php
// Admin creates a user via UI
$user = User::create([
    'email' => 'john@example.com',
    'fullname' => 'John Doe',
    'tier' => 'paid_pro',
]);

// Observer fires
// - Not in bulk mode ✓
// - Syncs immediately to CM
// - User gets cm_subscriber_id
```

### Example 3: User Profile Update (Normal Mode)

```php
// User updates their tier
$user->update(['tier' => 'paid_premium']);

// Observer fires
// - Detects 'tier' changed ✓
// - Syncs only 'tier' field to CM
// - Efficient partial update
```

### Example 4: Organization Change (Normal Mode)

```php
// Admin changes user's organization
$user->update(['organization_id' => 5]);

// Observer fires
// - Detects organization_id changed ✓
// - Syncs 'organization_name' to CM
// - Uses new organization's name
```

### Example 5: Temporarily Disable Auto-Sync

```php
// Before bulk operations
config(['campaign-monitor.disable_auto_sync' => true]);

// Perform many user operations
User::factory(1000)->create();

// Re-enable
config(['campaign-monitor.disable_auto_sync' => false]);

// Manually sync
php artisan cm:backfill-core-fields
```

## Configuration Options

### Environment Variables

```bash
# Campaign Monitor API credentials
CM_API_KEY=your-api-key-here
CM_CLIENT_ID=your-client-id-here
CM_LIST_ID=your-list-id-here

# Disable automatic syncing (default: false)
CM_DISABLE_AUTO_SYNC=false

# Bulk import batch size (default: 1000)
CM_BULK_IMPORT_BATCH_SIZE=1000
```

### Config File

```php
// config/campaign-monitor.php

return [
    'api_key' => env('CM_API_KEY'),
    'list_id' => env('CM_LIST_ID'),
    'disable_auto_sync' => env('CM_DISABLE_AUTO_SYNC', false),
    'bulk_import_batch_size' => env('CM_BULK_IMPORT_BATCH_SIZE', 1000),
];
```

## Best Practices

### 1. For Large Imports

**DO:**
- Let the system handle bulk mode automatically
- Run backfill command after import
- Use queue for backfill to avoid timeouts

**DON'T:**
- Try to sync during import
- Enable auto-sync for bulk operations

### 2. For Organization Imports

```php
// Organization admin imports users
// System automatically detects and skips sync

// After import, queue sync job
BulkSyncUsersJob::dispatch($organizationId);
```

### 3. For Testing

```php
// Disable sync during tests
config(['campaign-monitor.disable_auto_sync' => true]);

// Or use fake API
// Mock CS_REST_Subscribers class
```

### 4. For Monitoring

```php
// Check sync status
Log::info('CM Sync', [
    'user_id' => $user->id,
    'bulk_mode' => $this->isBulkOperation(),
    'sync_disabled' => $this->isSyncDisabled(),
    'fields_changed' => $this->getChangedCmFields($user),
]);
```

## Error Handling

The UserObserver never throws exceptions to avoid blocking user operations:

```php
try {
    $this->cmSyncService->syncUser($user, $fieldsToSync);
} catch (\Exception $e) {
    Log::error('UserObserver: Failed to sync user to CM', [
        'user_id' => $user->id,
        'exception' => $e->getMessage(),
    ]);
    // Operation continues ✓
}
```

If sync fails:
- Error is logged
- User operation completes successfully
- Admin can manually retry sync later

## Future Enhancements

### Queued Sync Option

```php
// For high-volume sites, queue individual syncs
protected function syncUserToCm(User $user, array $fieldsToSync = []): void
{
    if (config('campaign-monitor.queue_individual_syncs')) {
        SyncUserToCmJob::dispatch($user, $fieldsToSync);
    } else {
        $this->cmSyncService->syncUser($user, $fieldsToSync);
    }
}
```

### Sync Verification

```php
// Command to verify sync status
php artisan cm:verify-sync

// Shows:
// - Users in Laravel but not in CM
// - Users in CM but not in Laravel
// - Field mismatches
```

### Retry Failed Syncs

```php
// Track failed syncs
Schema::create('cm_sync_failures', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->text('error');
    $table->integer('retry_count')->default(0);
    $table->timestamp('next_retry_at')->nullable();
    $table->timestamps();
});

// Retry command
php artisan cm:retry-failed-syncs
```

## Summary

The Campaign Monitor sync strategy balances:
- **Performance** - Skip unnecessary syncs during bulk operations
- **Accuracy** - Sync immediately for individual operations
- **Reliability** - Never block user operations if CM fails
- **Flexibility** - Multiple ways to control sync behavior

Key takeaway: **Bulk imports skip UserObserver sync automatically**, avoiding thousands of unnecessary API calls.
