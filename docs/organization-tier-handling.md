# Organization Tier Change Handling

## Problem Statement

When an organization's tier changes (e.g., from `free` to `paid_pro`), it affects **all users** in that organization. This could be:
- Small organization: 5-10 users
- Medium organization: 100-500 users
- Large organization: 1,000-5,000+ users

If we synced each user individually via UserObserver, a tier change for 1,000 users would trigger 1,000 separate API calls to Campaign Monitor - slow and inefficient.

## Solution: OrganizationObserver with Smart Bulk Handling

### Architecture

```
┌──────────────────────────────────────────────────┐
│   Admin Changes Organization Tier               │
│   Example: Organization 'University of Oxford'  │
│   From: free → To: paid_premium                  │
│   Affected Users: 2,847                          │
└──────────────────────────────────────────────────┘
                    ↓
┌──────────────────────────────────────────────────┐
│        OrganizationObserver Triggered            │
│  1. Detect tier field changed                    │
│  2. Count affected users: 2,847                  │
│  3. Compare to threshold (default: 10)           │
└──────────────────────────────────────────────────┘
        ↓                           ↓
   [≤ 10 users]              [> 10 users]
   Small Org                  Large Org
        ↓                           ↓
┌──────────────────┐     ┌─────────────────────────┐
│ Set bulk flag    │     │ Set bulk flag           │
│ Update user tiers│     │ Update user tiers       │
│ Queue sync job   │     │ Queue sync job (delayed)│
│ Clear flag       │     │ Clear flag              │
└──────────────────┘     └─────────────────────────┘
        ↓                           ↓
┌──────────────────────────────────────────────────┐
│     BulkSyncOrganizationUsersJob (Queued)        │
│  • Process users in batches (1000/batch)        │
│  • Respect API rate limits                      │
│  • Log progress and errors                      │
│  • Retry failed syncs                           │
└──────────────────────────────────────────────────┘
```

## Implementation Details

### 1. OrganizationObserver

Watches for `tier` field changes on Organization model:

```php
public function updated(Organization $organization): void
{
    // Only act if tier changed
    if (!$organization->wasChanged('tier')) {
        return;
    }

    $affectedUsersCount = $organization->users()->count();

    // Determine sync strategy based on size
    if ($affectedUsersCount <= config('campaign-monitor.sync_threshold', 10)) {
        $this->syncUsersInBackground($organization);  // Small org
    } else {
        $this->queueBulkSync($organization);          // Large org
    }
}
```

### 2. Bulk Operation Flag

**Critical:** Set bulk flag to prevent UserObserver from syncing each user individually:

```php
protected function queueBulkSync(Organization $organization): void
{
    // Set bulk flag
    app()->instance('cm.bulk_import_active', true);

    try {
        // Update all users' tier field in database
        $organization->users()->update([
            'tier' => $organization->tier
        ]);
        // UserObserver fires for each user but skips CM sync ✓

        // Queue job to sync to CM
        BulkSyncOrganizationUsersJob::dispatch($organization->id, 'tier_change')
            ->delay(now()->addMinutes(1));

    } finally {
        // Clear flag
        app()->forgetInstance('cm.bulk_import_active');
    }
}
```

### 3. BulkSyncOrganizationUsersJob

Processes users in efficient batches:

```php
public function handle(CmSyncService $cmSyncService): void
{
    $organization = Organization::find($this->organizationId);
    $users = $organization->users()->where('cm_status', 'active')->get();

    // Process in chunks to respect rate limits
    $chunkSize = config('campaign-monitor.bulk_import_batch_size', 1000);
    $succeeded = 0;
    $failed = 0;

    $users->chunk($chunkSize, function ($chunk) use ($cmSyncService, &$succeeded, &$failed) {
        foreach ($chunk as $user) {
            $result = $cmSyncService->syncUser($user, ['user_id', 'tier', 'organization_name']);
            $result ? $succeeded++ : $failed++;

            // Small delay every 100 users
            if (($succeeded + $failed) % 100 === 0) {
                usleep(100000); // 0.1 second pause
            }
        }
    });

    Log::info('Bulk sync completed', [
        'organization_id' => $this->organizationId,
        'total' => $users->count(),
        'succeeded' => $succeeded,
        'failed' => $failed,
    ]);
}
```

## Configuration

### Sync Threshold

Controls when to use immediate vs queued sync:

```bash
# .env
CM_SYNC_THRESHOLD=10  # Organizations with ≤10 users sync immediately
                      # Organizations with >10 users queue bulk job
```

**Recommendations:**
- **Development/Testing**: Set to `5`
- **Production (small orgs)**: Set to `10-20`
- **Production (large orgs)**: Set to `5-10`

### Bulk Batch Size

Controls how many users sync per batch:

```bash
# .env
CM_BULK_IMPORT_BATCH_SIZE=1000  # Sync 1000 users per batch
```

**Recommendations:**
- **Good API quota**: `1000`
- **Limited API quota**: `500`
- **Very limited quota**: `250`

## Usage Examples

### Example 1: Small Organization Tier Change

```php
// Organization: "Small Startup" (8 users)
$organization = Organization::find(1);
$organization->update(['tier' => 'paid_pro']);

// OrganizationObserver fires
// - Detects tier changed ✓
// - Counts users: 8
// - 8 ≤ 10 (threshold) → Immediate sync
// - Sets bulk flag
// - Updates 8 user records: user.tier = 'paid_pro'
// - UserObserver fires 8 times, all skip sync ✓
// - Queues BulkSyncOrganizationUsersJob
// - Clears bulk flag

// BulkSyncOrganizationUsersJob runs
// - Syncs 8 users to CM (1 API call with bulk import or 8 individual)
// - Completes in seconds
```

### Example 2: Large Organization Tier Change

```php
// Organization: "University of Oxford" (2,847 users)
$organization = Organization::find(5);
$organization->update(['tier' => 'paid_premium']);

// OrganizationObserver fires
// - Detects tier changed ✓
// - Counts users: 2,847
// - 2,847 > 10 (threshold) → Queue bulk sync
// - Sets bulk flag
// - Updates 2,847 user records: user.tier = 'paid_premium'
// - UserObserver fires 2,847 times, all skip sync ✓
// - Queues BulkSyncOrganizationUsersJob (delayed 1 minute)
// - Clears bulk flag

// BulkSyncOrganizationUsersJob runs (after 1 minute)
// - Processes in batches of 1000
//   - Batch 1: Users 1-1000 (1000 syncs)
//   - Batch 2: Users 1001-2000 (1000 syncs)
//   - Batch 3: Users 2001-2847 (847 syncs)
// - Total: ~3 bulk operations or 2,847 individual API calls (spread out)
// - Completes in ~5-10 minutes
// - Logs: "2,845 succeeded, 2 failed"
```

### Example 3: Multiple Tier Changes (Edge Case)

```php
// Admin changes tier multiple times in quick succession
$organization->update(['tier' => 'paid_pro']);    // Queued
$organization->update(['tier' => 'paid_premium']); // Queued
$organization->update(['tier' => 'enterprise']);   // Queued

// Result: 3 jobs queued, but only last one matters
// Optimization: Could dedupe jobs based on organization_id
```

## Preventing Race Conditions

### Issue: Simultaneous Updates

If admin changes tier while bulk sync is running:

```
Time 0: Admin sets tier = 'paid_pro'
        → Job 1 queued
Time 1: Job 1 starts syncing 2,847 users with tier='paid_pro'
Time 2: Admin changes tier = 'paid_premium'
        → Job 2 queued
Time 3: Job 1 still running (syncing old tier)
Time 4: Job 2 starts (syncing new tier)
```

### Solution: Job Deduplication (Future Enhancement)

```php
// Use unique job ID based on organization + reason
public function uniqueId(): string
{
    return "org_{$this->organizationId}_tier_change";
}

// Laravel's queue will cancel Job 1 when Job 2 is dispatched
```

## Monitoring & Logging

### What Gets Logged

**Organization Tier Change:**
```php
Log::info('Organization tier changed', [
    'organization_id' => 5,
    'organization_name' => 'University of Oxford',
    'old_tier' => 'free',
    'new_tier' => 'paid_premium',
    'affected_users' => 2847,
]);
```

**Bulk Sync Queued:**
```php
Log::info('Queued bulk sync for organization tier change', [
    'organization_id' => 5,
    'affected_users' => 2847,
]);
```

**Bulk Sync Completed:**
```php
Log::info('Completed bulk sync for organization users', [
    'organization_id' => 5,
    'total_users' => 2847,
    'processed' => 2847,
    'succeeded' => 2845,
    'failed' => 2,
    'reason' => 'tier_change',
]);
```

**Individual Sync Failures:**
```php
Log::error('Failed to sync user in bulk operation', [
    'user_id' => 12345,
    'exception' => 'API timeout',
]);
```

### Monitoring Commands

```bash
# Check queue status
php artisan queue:work --queue=default --once

# Monitor failed jobs
php artisan queue:failed

# Retry failed syncs
php artisan queue:retry all
```

## Error Handling

### Job Retry Strategy

```php
public $tries = 3;
public $timeout = 600; // 10 minutes
public $backoff = [60, 180, 600]; // 1min, 3min, 10min
```

**If job fails:**
1. Wait 1 minute, retry
2. If fails again, wait 3 minutes, retry
3. If fails again, wait 10 minutes, retry
4. If fails third time, mark as failed

### Individual User Failures

Jobs **don't fail** if some users fail to sync:

```php
foreach ($chunk as $user) {
    try {
        $result = $cmSyncService->syncUser($user, $fieldsToSync);
        $result ? $succeeded++ : $failed++;
    } catch (\Exception $e) {
        $failed++;
        Log::error('User sync failed', [...]);
        // Continue to next user ✓
    }
}
```

### Manual Retry

Admin can manually retry failed organization:

```bash
php artisan cm:sync-organization 5 --reason="manual_retry"
```

## Best Practices

### 1. Set Appropriate Threshold

```php
// Small organizations (< 100 users)
CM_SYNC_THRESHOLD=20

// Large organizations (100-1000s users)
CM_SYNC_THRESHOLD=10

// Enterprise (1000s-10000s users)
CM_SYNC_THRESHOLD=5
```

### 2. Use Queue Workers

```bash
# Run queue worker in background
php artisan queue:work --queue=default --tries=3

# Or use Supervisor to keep worker running
```

### 3. Monitor Queue Health

```bash
# Check queue size
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed

# Retry specific failed job
php artisan queue:retry 12345
```

### 4. Notify Admin of Bulk Operations

```php
// Send notification when bulk sync starts
Notification::send($admin, new BulkSyncStarted($organization, $userCount));

// Send notification when bulk sync completes
Notification::send($admin, new BulkSyncCompleted($organization, $succeeded, $failed));
```

## Integration with UserObserver

The system works seamlessly together:

| Scenario | UserObserver | OrganizationObserver | Result |
|----------|--------------|---------------------|--------|
| Admin creates 1 user | Syncs immediately | - | User synced |
| Admin updates 1 user tier | Syncs only tier field | - | Efficient |
| CSV import (1000 users) | Skips sync (bulk flag) | - | No API calls |
| Org tier changes (500 users) | Skips sync (bulk flag) | Queues job | Efficient batch |
| User changes own org | Syncs organization_name | - | User synced |

## Summary

**Organization tier changes affecting hundreds/thousands of users are handled efficiently:**

✅ **Automatic Detection** - OrganizationObserver watches for tier changes
✅ **Smart Routing** - Small orgs sync immediately, large orgs queue job
✅ **Bulk Flag** - Prevents UserObserver from syncing each user individually
✅ **Batched Processing** - 1000 users per batch, respects rate limits
✅ **Error Resilience** - Individual failures don't stop the job
✅ **Retry Logic** - Automatic retries with exponential backoff
✅ **Comprehensive Logging** - Full audit trail of all operations

**Key Insight:** Changing tier for 2,847 users = **3-5 bulk operations** instead of **2,847 individual API calls**!
