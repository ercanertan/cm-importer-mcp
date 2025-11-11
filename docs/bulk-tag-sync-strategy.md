# Bulk Tag Sync Strategy: Avoiding API Call Storms

## The Problem

If we sync tags individually via UserObserver, we recreate the exact problem we're trying to solve:

### Scenario 1: Organization Tier Change
```
Organization "University of Oxford" changes tier: free → paid_premium
↓
2,847 users need tier tag updated
↓
UserObserver fires 2,847 times
↓
2,847 API calls to CM to update tags
↓
❌ API CALL STORM! (Same problem as before)
```

### Scenario 2: Bulk Import
```
Admin imports 10,000 users from CSV
↓
10,000 users created
↓
UserObserver::created() fires 10,000 times
↓
10,000 API calls to sync tags
↓
❌ HOURS TO COMPLETE!
```

### Scenario 3: Engagement Score Recalculation
```
Nightly job recalculates engagement scores for 60,000 users
↓
UserObserver::updated() fires 60,000 times
↓
60,000 API calls to update engagement tags
↓
❌ IMPOSSIBLE!
```

---

## The Solution: Smart Observer + Bulk Tag Jobs

### Core Principle
**Observer detects changes but DOESN'T sync tags directly**
Instead: Queue a bulk job to sync tags efficiently

---

## Architecture

```
┌─────────────────────────────────────────────────────┐
│         User/Organization Change Detected            │
└─────────────────────────────────────────────────────┘
                     ↓
┌─────────────────────────────────────────────────────┐
│              Observer Checks Context                 │
│  • Is this a bulk operation?                        │
│  • How many users affected?                         │
│  • What changed? (tier, engagement, status)         │
└─────────────────────────────────────────────────────┘
        ↓                              ↓
   [Single User]                  [Bulk Operation]
        ↓                              ↓
  Skip Tag Sync                 Queue Bulk Tag Job
  (or queue individual)          (Delayed, batched)
        ↓                              ↓
                            Process in batches of 1000
                                       ↓
                            Use CM Import API with tags
```

---

## Implementation

### 1. UserObserver: Don't Sync Tags Individually

```php
// app/Observers/UserObserver.php

public function updated(User $user): void
{
    // Skip ALL CM syncing during bulk operations
    if ($this->isBulkOperation()) {
        // Just track what changed in a queue for later
        $this->queueTagSyncForLater($user);
        return;
    }

    // For individual user updates, ALSO skip immediate tag sync
    // Instead, queue it with a short delay to batch with other changes
    if ($user->wasChanged(['tier', 'engagement_score', 'last_activity_at'])) {
        // Delay by 5 seconds to allow batching
        SyncUserTagsJob::dispatch($user->id)
            ->delay(now()->addSeconds(5));
    }

    // Sync core custom fields to CM (minimal API call)
    if ($this->hasCmRelevantChanges($user)) {
        $fieldsToSync = $this->getChangedCmFields($user);
        app(CmSyncService::class)->syncUser($user, $fieldsToSync);
    }
}

protected function queueTagSyncForLater(User $user): void
{
    // Store user IDs that need tag sync in cache
    // Will be processed by bulk job after import completes
    Cache::increment('pending_tag_sync_count');
    Cache::push('pending_tag_sync_user_ids', $user->id);
}
```

### 2. Organization Observer: Queue Bulk Tag Job

```php
// app/Observers/OrganizationObserver.php

public function updated(Organization $organization): void
{
    if (!$organization->wasChanged('tier')) {
        return;
    }

    $affectedUsersCount = $organization->users()->count();

    // Set bulk flag to prevent UserObserver from syncing
    app()->instance('cm.bulk_import_active', true);

    try {
        // Update all users' tier field in database
        $organization->users()->update([
            'tier' => $organization->tier
        ]);
        // UserObserver fires but skips CM sync ✓

        // Queue bulk tag sync job (delayed to let DB updates finish)
        BulkSyncTagsJob::dispatch(
            reason: 'org_tier_change',
            organizationId: $organization->id,
            newTier: $organization->tier
        )->delay(now()->addMinutes(1));

        Log::info('Queued bulk tag sync for organization tier change', [
            'organization_id' => $organization->id,
            'new_tier' => $organization->tier,
            'affected_users' => $affectedUsersCount,
        ]);

    } finally {
        app()->forgetInstance('cm.bulk_import_active');
    }
}
```

### 3. Bulk Tag Sync Job: Use CM Import API

Campaign Monitor's **Import API** allows updating tags in bulk!

```php
// app/Jobs/BulkSyncTagsJob.php

class BulkSyncTagsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $reason, // 'org_tier_change', 'bulk_import', 'engagement_recalc'
        public ?int $organizationId = null,
        public ?string $newTier = null,
        public ?array $userIds = null
    ) {}

    public function handle(CmSyncService $cmSyncService): void
    {
        // Get users that need tag sync
        $users = $this->getUsersToSync();

        if ($users->isEmpty()) {
            Log::info('BulkSyncTagsJob: No users to sync');
            return;
        }

        Log::info('BulkSyncTagsJob: Starting bulk tag sync', [
            'reason' => $this->reason,
            'user_count' => $users->count(),
        ]);

        // Process in chunks of 1000
        $chunks = $users->chunk(1000);
        $totalSuccess = 0;
        $totalFailed = 0;

        foreach ($chunks as $index => $chunk) {
            $subscribers = $this->prepareSubscribersWithTags($chunk);

            $result = $cmSyncService->bulkImport($subscribers);

            $totalSuccess += $result['success'];
            $totalFailed += $result['failed'];

            Log::info('BulkSyncTagsJob: Batch completed', [
                'batch' => $index + 1,
                'total_batches' => $chunks->count(),
                'success' => $result['success'],
                'failed' => $result['failed'],
            ]);

            // Small delay between batches
            if ($index < $chunks->count() - 1) {
                usleep(200000); // 0.2 seconds
            }
        }

        Log::info('BulkSyncTagsJob: Completed', [
            'reason' => $this->reason,
            'total_users' => $users->count(),
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
        ]);
    }

    protected function getUsersToSync(): Collection
    {
        if ($this->organizationId) {
            // Org tier change: get all users in org
            return User::where('organization_id', $this->organizationId)
                ->where('cm_status', 'active')
                ->get();
        }

        if ($this->userIds) {
            // Specific users
            return User::whereIn('id', $this->userIds)
                ->where('cm_status', 'active')
                ->get();
        }

        return collect();
    }

    protected function prepareSubscribersWithTags(Collection $users): array
    {
        return $users->map(function ($user) {
            return [
                'EmailAddress' => $user->email,
                'Name' => $user->fullname,
                'CustomFields' => [
                    [
                        'Key' => 'user_id',
                        'Value' => (string) $user->id,
                    ],
                    [
                        'Key' => 'tier',
                        'Value' => $user->tier ?? 'free',
                    ],
                    [
                        'Key' => 'engagement_score',
                        'Value' => (string) $user->engagement_score,
                    ],
                ],
                // THIS IS THE KEY: Update tags via Import API
                'ConsentToTrack' => $user->permission_to_track ? 'Yes' : 'No',
                'Resubscribe' => false,
                'RestartSubscriptionBasedAutoresponders' => false,

                // Remove old tags and add new ones
                // Note: CM Import API doesn't directly support tag updates
                // We need a different approach...
            ];
        })->toArray();
    }
}
```

**PROBLEM**: CM's Import API doesn't support tag updates directly!

---

## Solution: Hybrid Approach

### Strategy: Store Tags in Laravel, Sync via Scheduled Job

**Key Insight**: We don't need real-time tag sync. Tags are only used for segment targeting, which happens when campaigns are scheduled (not real-time).

### New Architecture:

```
┌─────────────────────────────────────────────────────┐
│              User/Org Change Happens                 │
└─────────────────────────────────────────────────────┘
                     ↓
         Update user.tier in Laravel DB
                     ↓
              UserObserver fires
                     ↓
         Skip CM tag sync (bulk or individual)
                     ↓
     Mark user as "tags_need_sync" in DB
                     ↓
         Continue (user operation completes)
                     ↓
                     ↓
         [Later: Scheduled Job Runs]
                     ↓
    Find all users with tags_need_sync = true
                     ↓
      Sync their tags to CM in batches
                     ↓
         Set tags_need_sync = false
```

---

## Implementation: Tags Stored in Laravel

### 1. Add Migration for Tag Sync Tracking

```php
// database/migrations/2025_11_11_create_user_cm_tags_table.php

Schema::create('user_cm_tags', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('tag'); // e.g., 'tier:paid_pro'
    $table->boolean('synced_to_cm')->default(false);
    $table->timestamp('synced_at')->nullable();
    $table->timestamps();

    $table->unique(['user_id', 'tag']);
    $table->index(['synced_to_cm', 'updated_at']); // For finding pending syncs
});

// Or simpler: Just track what needs sync
Schema::table('users', function (Blueprint $table) {
    $table->boolean('cm_tags_need_sync')->default(false)->after('cm_subscriber_id');
    $table->timestamp('cm_tags_synced_at')->nullable()->after('cm_tags_need_sync');
});
```

### 2. UserObserver: Just Mark for Sync

```php
// app/Observers/UserObserver.php

public function updated(User $user): void
{
    // Skip during bulk operations
    if ($this->isBulkOperation()) {
        return;
    }

    // If tier/engagement/status changed, mark for tag sync
    if ($user->wasChanged(['tier', 'engagement_score', 'last_activity_at'])) {
        $user->cm_tags_need_sync = true;
        $user->saveQuietly(); // Don't trigger observer again
    }

    // Still sync core custom fields immediately (these are few)
    if ($this->hasCmRelevantChanges($user)) {
        $fieldsToSync = $this->getChangedCmFields($user);
        app(CmSyncService::class)->syncUser($user, $fieldsToSync);
    }
}
```

### 3. Scheduled Job: Sync Tags in Batches

```php
// app/Console/Commands/SyncCampaignMonitorTags.php

class SyncCampaignMonitorTags extends Command
{
    protected $signature = 'cm:sync-tags {--force}';
    protected $description = 'Sync pending tag changes to Campaign Monitor';

    public function handle(): void
    {
        // Find users that need tag sync
        $users = User::where('cm_tags_need_sync', true)
            ->where('cm_status', 'active')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users need tag sync.');
            return;
        }

        $this->info("Syncing tags for {$users->count()} users...");

        // Group by tags that need updating
        $tagUpdates = $this->calculateTagUpdates($users);

        // Use CM API to add/remove tags in bulk
        foreach ($tagUpdates as $action => $tagData) {
            foreach ($tagData as $tag => $userEmails) {
                $this->syncTag($action, $tag, $userEmails);
            }
        }

        // Mark users as synced
        User::whereIn('id', $users->pluck('id'))
            ->update([
                'cm_tags_need_sync' => false,
                'cm_tags_synced_at' => now(),
            ]);

        $this->info('Tag sync complete!');
    }

    protected function calculateTagUpdates(Collection $users): array
    {
        $add = [];
        $remove = [];

        foreach ($users as $user) {
            // Determine which tags this user should have
            $currentTags = $this->getCurrentTags($user);
            $expectedTags = $this->getExpectedTags($user);

            // Tags to add
            $toAdd = array_diff($expectedTags, $currentTags);
            foreach ($toAdd as $tag) {
                $add[$tag][] = $user->email;
            }

            // Tags to remove
            $toRemove = array_diff($currentTags, $expectedTags);
            foreach ($toRemove as $tag) {
                $remove[$tag][] = $user->email;
            }
        }

        return ['add' => $add, 'remove' => $remove];
    }

    protected function getExpectedTags(User $user): array
    {
        $tags = [];

        // Tier tag
        if ($user->tier) {
            $tags[] = 'tier:' . $user->tier;
        }

        // Engagement tag
        if ($user->engagement_score >= 70) {
            $tags[] = 'engagement:high';
        } elseif ($user->engagement_score >= 40) {
            $tags[] = 'engagement:medium';
        } else {
            $tags[] = 'engagement:low';
        }

        // Status tag
        if ($user->last_activity_at && $user->last_activity_at->gt(now()->subDays(30))) {
            $tags[] = 'status:active';
        } else {
            $tags[] = 'status:inactive';
        }

        // Org tier tag
        if ($user->organization) {
            $tags[] = 'org_tier:' . $user->organization->tier;
        }

        return $tags;
    }

    protected function syncTag(string $action, string $tag, array $emails): void
    {
        // Batch emails into groups of 1000
        $batches = array_chunk($emails, 1000);

        foreach ($batches as $batch) {
            $this->syncTagBatch($action, $tag, $batch);
        }
    }

    protected function syncTagBatch(string $action, string $tag, array $emails): void
    {
        try {
            $subscribers = new \CS_REST_Subscribers(
                config('campaign-monitor.list_id'),
                ['api_key' => config('campaign-monitor.api_key')]
            );

            if ($action === 'add') {
                $result = $subscribers->add_tags_bulk($emails, [$tag]);
            } else {
                $result = $subscribers->remove_tags_bulk($emails, [$tag]);
            }

            if ($result->was_successful()) {
                $this->info("  ✓ {$action}: {$tag} for " . count($emails) . " users");
            } else {
                $this->error("  ✗ Failed to {$action} tag: {$tag}");
            }

        } catch (\Exception $e) {
            $this->error("  ✗ Exception: {$e->getMessage()}");
        }
    }

    /**
     * Get current tags for user from CM (or cache)
     * This requires querying CM API or maintaining tag state in Laravel
     */
    protected function getCurrentTags(User $user): array
    {
        // Option 1: Store in Laravel DB (recommended)
        return DB::table('user_cm_tags')
            ->where('user_id', $user->id)
            ->where('synced_to_cm', true)
            ->pluck('tag')
            ->toArray();

        // Option 2: Query CM API (slower, but accurate)
        // return app(CampaignMonitorTagService::class)->getUserTags($user);
    }
}

// Schedule it
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Sync tags every 15 minutes
    $schedule->command('cm:sync-tags')->everyFifteenMinutes();

    // Or every 5 minutes for faster sync
    // $schedule->command('cm:sync-tags')->everyFiveMinutes();
}
```

---

## API Efficiency Comparison

### Scenario: Organization Tier Change (2,847 users)

**Old Approach (Individual Tag API Calls)**:
```
UserObserver fires 2,847 times
→ 2,847 API calls to update tags
→ ~1-2 hours to complete
→ ❌ TERRIBLE
```

**New Approach (Batched Tag Sync)**:
```
UserObserver fires 2,847 times (marks cm_tags_need_sync = true)
→ 0 API calls immediately
→ Scheduled job runs (every 15 min)
→ Finds 2,847 users need sync
→ Calculates tag changes:
   • Remove 'tier:free' from 2,847 users
   • Add 'tier:paid_premium' to 2,847 users
→ Bulk API calls:
   • remove_tags_bulk('tier:free', [2847 emails in 3 batches of 1000])
   • add_tags_bulk('tier:paid_premium', [2847 emails in 3 batches of 1000])
→ Total: 6 API calls
→ Completes in ~30 seconds
→ ✅ PERFECT!
```

---

## Summary

### Key Principles:

1. **Never sync tags individually** - Always batch
2. **Mark, don't sync** - Observers mark users for sync, don't execute
3. **Scheduled sync** - Background job syncs every 5-15 minutes
4. **Use bulk API** - `add_tags_bulk()` and `remove_tags_bulk()`
5. **Track in Laravel** - Store expected tags in DB for comparison

### Benefits:

✅ **2,847 users** = 6 API calls (not 2,847)
✅ **60,000 users** = ~120 API calls (not 60,000)
✅ **Works with bulk imports** - Tags synced after import completes
✅ **Works with org tier changes** - No API storm
✅ **Eventual consistency** - Tags sync within 5-15 minutes (acceptable for campaigns)

### Trade-off:

⚠️ **Not real-time** - Tags sync every 5-15 minutes
✅ **Acceptable** - Campaigns are scheduled, not sent instantly

---

*Next: Implement user_cm_tags table and scheduled sync command.*
