# CDP Downtime Recovery & Queue Management UI

**Date Created:** 2025-11-12
**Document Version:** 1.0
**Status:** Implementation Plan
**Related Docs:** CDP_PROJECT_PLAN.md, CDP_WORKFLOWS.md, CDP_UI_SPECIFICATIONS.md

---

## Executive Summary

This document specifies the UI-based queue management system for handling Campaign Monitor (CM) downtime and failed jobs. The solution eliminates the need for CLI access by providing admin dashboard tools for monitoring, retrying, and recovering from CM API outages.

---

## Problem Statement

### Current Limitation

When Campaign Monitor experiences downtime (e.g., 1 hour outage):

1. ✅ **Jobs queue correctly** (database persistence)
2. ✅ **Auto-retry 3 times** with exponential backoff (1min, 5min, 15min)
3. ❌ **Manual intervention required via CLI**: `php artisan queue:retry all`
4. ❌ **No visual monitoring** of failed jobs
5. ❌ **No CM health status indicator**

### Required Solution

**Admins need a UI to:**
- Monitor CM API health status (online/offline)
- View all failed jobs with details
- Retry individual failed jobs
- Bulk retry all failed jobs
- See queue statistics (pending, processing, failed)
- Receive notifications when CM recovers

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│         Admin: Sync Status Dashboard (/admin/cdp/sync-status)         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────────────────────────────────────────┐  │
│  │  CM API Health Check (Live)                     │  │
│  │  ● ONLINE / ● OFFLINE / ● DEGRADED              │  │
│  │  Last checked: 2s ago (auto-refresh 10s)        │  │
│  └─────────────────────────────────────────────────┘  │
│                                                         │
│  ┌─────────────────────────────────────────────────┐  │
│  │  Queue Statistics                                │  │
│  │  Pending: 23  |  Processing: 5  |  Failed: 47   │  │
│  │  cm-sync: 12  |  cm-campaigns: 30  |  cleanup: 5 │  │
│  └─────────────────────────────────────────────────┘  │
│                                                         │
│  ┌─────────────────────────────────────────────────┐  │
│  │  Failed Jobs (47)                [Retry All]     │  │
│  ├─────────────────────────────────────────────────┤  │
│  │  Job: AddSubscriberToCm                          │  │
│  │  Queue: cm-sync                                  │  │
│  │  Failed: 5 mins ago                              │  │
│  │  Error: cURL error 7: Failed to connect         │  │
│  │  Payload: User #12345                            │  │
│  │                            [View] [Retry] [Delete]│  │
│  ├─────────────────────────────────────────────────┤  │
│  │  Job: TagAndSendCampaignJob                      │  │
│  │  Queue: cm-campaigns                             │  │
│  │  Failed: 12 mins ago                             │  │
│  │  Error: Network timeout after 30s                │  │
│  │  Payload: Campaign #234 (2,345 users)            │  │
│  │                            [View] [Retry] [Delete]│  │
│  └─────────────────────────────────────────────────┘  │
│                                                         │
│  [Bulk Actions: Retry All | Retry Selected | Clear All]│
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Implementation Specification

### 1. Sync Status Dashboard (Primary Feature)

**Route:** `/admin/cdp/sync-status`
**Component:** `app/Livewire/Admin/SyncStatusDashboard.php`
**View:** `resources/views/livewire/admin/sync-status-dashboard.blade.php`

#### Features

##### A. CM API Health Check

```php
// app/Actions/CampaignMonitor/CheckHealthAction.php

class CheckHealthAction
{
    public function execute(): array
    {
        try {
            $start = microtime(true);

            // Ping CM API (lightweight endpoint)
            $cm = app(CampaignMonitorService::class);
            $cm->getClients(); // Simple API call

            $latency = round((microtime(true) - $start) * 1000); // ms

            return [
                'status' => 'online',
                'latency' => $latency,
                'message' => "Connected ({$latency}ms)",
                'checked_at' => now()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'offline',
                'latency' => null,
                'message' => $e->getMessage(),
                'checked_at' => now()
            ];
        }
    }
}
```

**UI Display:**
```blade
<div class="flex items-center gap-3 p-4 rounded-lg border
    {{ $cmHealth['status'] === 'online'
        ? 'bg-green-50 border-green-200 dark:bg-green-900/20'
        : 'bg-red-50 border-red-200 dark:bg-red-900/20' }}">

    <div class="w-3 h-3 rounded-full
        {{ $cmHealth['status'] === 'online' ? 'bg-green-500 animate-pulse' : 'bg-red-500' }}">
    </div>

    <div>
        <p class="font-medium {{ $cmHealth['status'] === 'online' ? 'text-green-900 dark:text-green-100' : 'text-red-900 dark:text-red-100' }}">
            Campaign Monitor: {{ strtoupper($cmHealth['status']) }}
        </p>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ $cmHealth['message'] }} • Last checked {{ $cmHealth['checked_at']->diffForHumans() }}
        </p>
    </div>

    <button wire:click="checkHealth" class="ml-auto">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
    </button>
</div>
```

##### B. Queue Statistics

```php
// In SyncStatusDashboard Livewire component

public function getQueueStatsProperty(): array
{
    return [
        'pending' => DB::table('jobs')->count(),
        'processing' => DB::table('jobs')->whereNotNull('reserved_at')->count(),
        'failed' => DB::table('failed_jobs')->count(),
        'by_queue' => [
            'cm-sync' => DB::table('failed_jobs')->where('queue', 'cm-sync')->count(),
            'cm-campaigns' => DB::table('failed_jobs')->where('queue', 'cm-campaigns')->count(),
            'cm-cleanup' => DB::table('failed_jobs')->where('queue', 'cm-cleanup')->count(),
            'webhooks' => DB::table('failed_jobs')->where('queue', 'webhooks')->count(),
        ]
    ];
}
```

**UI Display:**
```blade
<div class="grid grid-cols-3 gap-4">
    <div class="bg-white dark:bg-zinc-800 rounded-lg p-4 border">
        <p class="text-sm text-gray-600 dark:text-gray-400">Pending</p>
        <p class="text-2xl font-bold">{{ $queueStats['pending'] }}</p>
    </div>

    <div class="bg-white dark:bg-zinc-800 rounded-lg p-4 border">
        <p class="text-sm text-gray-600 dark:text-gray-400">Processing</p>
        <p class="text-2xl font-bold">{{ $queueStats['processing'] }}</p>
    </div>

    <div class="bg-white dark:bg-zinc-800 rounded-lg p-4 border">
        <p class="text-sm text-gray-600 dark:text-gray-400">Failed</p>
        <p class="text-2xl font-bold text-red-600">{{ $queueStats['failed'] }}</p>
    </div>
</div>
```

##### C. Failed Jobs List

```php
// In SyncStatusDashboard Livewire component

public function getFailedJobsProperty()
{
    return DB::table('failed_jobs')
        ->orderBy('failed_at', 'desc')
        ->paginate(20);
}

public function retryJob($id)
{
    Artisan::call('queue:retry', ['id' => [$id]]);

    $this->dispatch('job-retried');
    session()->flash('success', 'Job queued for retry.');
}

public function retryAllFailedJobs()
{
    $count = DB::table('failed_jobs')->count();

    Artisan::call('queue:retry', ['id' => ['all']]);

    $this->dispatch('jobs-retried');
    session()->flash('success', "{$count} jobs queued for retry.");
}

public function deleteJob($id)
{
    DB::table('failed_jobs')->where('id', $id)->delete();

    session()->flash('success', 'Failed job deleted.');
}

public function clearAllFailedJobs()
{
    DB::table('failed_jobs')->truncate();

    session()->flash('success', 'All failed jobs cleared.');
}
```

**UI Display:**
```blade
<div class="bg-white dark:bg-zinc-800 rounded-lg border">
    <div class="p-4 border-b flex items-center justify-between">
        <h2 class="text-lg font-semibold">Failed Jobs ({{ $queueStats['failed'] }})</h2>

        <div class="flex gap-2">
            <flux:button
                wire:click="retryAllFailedJobs"
                size="sm"
                variant="filled"
                :disabled="$queueStats['failed'] === 0">
                Retry All
            </flux:button>

            <flux:button
                wire:click="$set('showClearConfirm', true)"
                size="sm"
                variant="ghost"
                :disabled="$queueStats['failed'] === 0">
                Clear All
            </flux:button>
        </div>
    </div>

    <div class="divide-y">
        @forelse($failedJobs as $job)
            <div class="p-4 hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <p class="font-medium text-gray-900 dark:text-white">
                                {{ class_basename($job->payload_decoded->displayName ?? 'Unknown Job') }}
                            </p>

                            <span class="px-2 py-1 text-xs rounded-md bg-gray-100 dark:bg-gray-700">
                                {{ $job->queue }}
                            </span>
                        </div>

                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Failed {{ \Carbon\Carbon::parse($job->failed_at)->diffForHumans() }}
                        </p>

                        <div class="mt-2 text-sm text-red-600 dark:text-red-400 font-mono">
                            {{ Str::limit($job->exception, 150) }}
                        </div>

                        @if($job->payload_decoded)
                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                <strong>Payload:</strong>
                                @if(isset($job->payload_decoded->data->userId))
                                    User #{{ $job->payload_decoded->data->userId }}
                                @elseif(isset($job->payload_decoded->data->campaignId))
                                    Campaign #{{ $job->payload_decoded->data->campaignId }}
                                @else
                                    {{ Str::limit(json_encode($job->payload_decoded->data), 100) }}
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="flex gap-2 ml-4">
                        <flux:button
                            wire:click="$set('viewJobId', {{ $job->id }})"
                            size="sm"
                            variant="ghost">
                            View
                        </flux:button>

                        <flux:button
                            wire:click="retryJob({{ $job->id }})"
                            size="sm"
                            variant="filled">
                            Retry
                        </flux:button>

                        <flux:button
                            wire:click="deleteJob({{ $job->id }})"
                            size="sm"
                            variant="ghost">
                            Delete
                        </flux:button>
                    </div>
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="font-medium">No Failed Jobs</p>
                <p class="text-sm mt-1">All jobs are processing successfully</p>
            </div>
        @endforelse
    </div>

    @if($failedJobs->hasPages())
        <div class="p-4 border-t">
            {{ $failedJobs->links() }}
        </div>
    @endif
</div>
```

##### D. Job Detail Modal

```blade
@if($viewJobId)
    <flux:modal wire:model="viewJobId" class="max-w-3xl">
        <flux:modal.content>
            <flux:heading size="lg">Job Details</flux:heading>

            @php
                $job = DB::table('failed_jobs')->find($viewJobId);
                $payload = json_decode($job->payload);
            @endphp

            <div class="space-y-4 mt-4">
                <div>
                    <label class="text-sm font-medium">Job Class</label>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ $payload->displayName ?? 'Unknown' }}
                    </p>
                </div>

                <div>
                    <label class="text-sm font-medium">Queue</label>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $job->queue }}</p>
                </div>

                <div>
                    <label class="text-sm font-medium">Failed At</label>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ \Carbon\Carbon::parse($job->failed_at)->format('M d, Y H:i:s') }}
                        ({{ \Carbon\Carbon::parse($job->failed_at)->diffForHumans() }})
                    </p>
                </div>

                <div>
                    <label class="text-sm font-medium">Exception</label>
                    <pre class="text-xs bg-red-50 dark:bg-red-900/20 p-3 rounded mt-1 overflow-x-auto">{{ $job->exception }}</pre>
                </div>

                <div>
                    <label class="text-sm font-medium">Payload</label>
                    <pre class="text-xs bg-gray-50 dark:bg-gray-800 p-3 rounded mt-1 overflow-x-auto">{{ json_encode($payload, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>

            <flux:modal.footer>
                <flux:button wire:click="retryJob({{ $viewJobId }})" variant="filled">
                    Retry Job
                </flux:button>
                <flux:button wire:click="$set('viewJobId', null)" variant="ghost">
                    Close
                </flux:button>
            </flux:modal.footer>
        </flux:modal.content>
    </flux:modal>
@endif
```

##### E. Real-Time Updates

```php
// In SyncStatusDashboard Livewire component

protected $listeners = ['job-retried' => '$refresh', 'jobs-retried' => '$refresh'];

// Blade template
<div wire:poll.10s>
    {{-- All dashboard content auto-refreshes every 10 seconds --}}
</div>
```

---

### 2. Navigation Integration

**File:** `resources/views/components/layouts/app/sidebar.blade.php`

Already specified in CDP_UI_SPECIFICATIONS.md (line 130):

```blade
<flux:navlist.item
    icon="zap"
    :href="route('admin.cdp.sync-status')"
    :current="request()->routeIs('admin.cdp.sync-status')"
    wire:navigate>
    Sync Status
</flux:navlist.item>
```

Add badge for failed job count:

```blade
<flux:navlist.item
    icon="zap"
    :href="route('admin.cdp.sync-status')"
    :current="request()->routeIs('admin.cdp.sync-status')"
    wire:navigate>
    Sync Status

    @if($failedJobsCount > 0)
        <span class="ml-auto px-2 py-0.5 text-xs font-bold rounded-full bg-red-500 text-white">
            {{ $failedJobsCount }}
        </span>
    @endif
</flux:navlist.item>
```

---

### 3. Campaign List Enhancements

**File:** `app/Livewire/Admin/Campaigns/Index.php`

Add bulk retry for failed campaigns:

```php
public function retrySelectedCampaigns()
{
    $campaigns = Campaign::whereIn('id', $this->selectedCampaigns)
        ->where('status', 'failed')
        ->get();

    foreach ($campaigns as $campaign) {
        TagAndSendCampaignJob::dispatch(
            $campaign->id,
            $campaign->userIds,
            $campaign->campaign_tag
        )->onQueue('cm-campaigns');

        $campaign->update([
            'status' => 'pending',
            'campaign_tag_status' => 'pending'
        ]);
    }

    session()->flash('success', "{$campaigns->count()} campaigns queued for retry.");
    $this->selectedCampaigns = [];
}
```

**UI Addition:**
```blade
@if($selectedCampaigns && count($selectedCampaigns) > 0)
    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg mb-4">
        <div class="flex items-center justify-between">
            <p class="text-sm">{{ count($selectedCampaigns) }} campaigns selected</p>

            <div class="flex gap-2">
                <flux:button wire:click="retrySelectedCampaigns" size="sm">
                    Retry Selected
                </flux:button>
                <flux:button wire:click="$set('selectedCampaigns', [])" size="sm" variant="ghost">
                    Clear Selection
                </flux:button>
            </div>
        </div>
    </div>
@endif
```

---

### 4. Auto-Recovery System (Optional - Phase 2)

**Command:** `app/Console/Commands/MonitorCampaignMonitorHealth.php`

```php
<?php

namespace App\Console\Commands;

use App\Actions\CampaignMonitor\CheckHealthAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\CampaignMonitorRecoveredNotification;

class MonitorCampaignMonitorHealth extends Command
{
    protected $signature = 'cm:monitor-health';
    protected $description = 'Check CM API health and auto-retry failed jobs on recovery';

    public function handle(CheckHealthAction $checkHealth): int
    {
        $health = $checkHealth->execute();
        $previousStatus = Cache::get('cm_health_status', 'unknown');

        // Store current status
        Cache::put('cm_health_status', $health['status'], now()->addMinutes(10));

        // If CM just recovered (was offline, now online)
        if ($previousStatus === 'offline' && $health['status'] === 'online') {
            $this->info('Campaign Monitor recovered! Auto-retrying failed jobs...');

            $failedCount = DB::table('failed_jobs')->count();

            if ($failedCount > 0) {
                // Retry all failed jobs
                Artisan::call('queue:retry', ['id' => ['all']]);

                $this->info("✓ {$failedCount} failed jobs queued for retry");

                // Notify admins
                $admins = \App\Models\User::where('role', 'admin')->get();
                Notification::send($admins, new CampaignMonitorRecoveredNotification($failedCount));

                return Command::SUCCESS;
            }
        }

        $this->info("Campaign Monitor status: {$health['status']}");

        return Command::SUCCESS;
    }
}
```

**Schedule in `app/Console/Kernel.php`:**

```php
protected function schedule(Schedule $schedule): void
{
    // Check CM health every 5 minutes
    $schedule->command('cm:monitor-health')
        ->everyFiveMinutes()
        ->withoutOverlapping();
}
```

---

## User Flow: Handling 1-Hour CM Outage

### Timeline

```
14:00 - CM goes offline
14:01 - User sync job fails (attempt 1)
14:02 - Retry 1 fails (attempt 2)
14:07 - Retry 2 fails (attempt 3)
14:22 - Retry 3 fails → Job marked as FAILED
14:22 - Campaign send job fails → FAILED
14:22 - 47 jobs in failed_jobs table

[Admin checks Sync Status Dashboard]
14:25 - Admin sees: "Campaign Monitor: OFFLINE"
14:25 - Admin sees: "47 Failed Jobs"
14:25 - Admin waits...

15:00 - CM comes back online
15:00 - Auto-health check detects recovery (optional)
15:00 - Admin notification: "CM Recovered - 47 jobs auto-retried" (optional)

[OR Manual Recovery:]
15:01 - Admin refreshes dashboard
15:01 - Admin sees: "Campaign Monitor: ONLINE"
15:01 - Admin clicks: "Retry All Failed Jobs"
15:02 - All 47 jobs queued for processing
15:03 - Queue workers process jobs successfully
15:05 - Dashboard shows: "0 Failed Jobs" ✅
```

### Admin Actions Available

1. **Monitor Health**: Click refresh icon to manually check CM status
2. **View Job Details**: Click "View" to see full exception + payload
3. **Retry Individual Job**: Click "Retry" on specific job
4. **Retry All Jobs**: Click "Retry All" button (bulk action)
5. **Clear Failed Jobs**: Delete failed jobs that are no longer needed
6. **Campaign Bulk Retry**: Select failed campaigns and retry

---

## Benefits

### For Admins
✅ **No CLI access required** - Everything in UI
✅ **Visual monitoring** - See exactly what's failing
✅ **One-click recovery** - Retry all with single button
✅ **Transparency** - Full job details, exceptions, payloads
✅ **Real-time updates** - Auto-refresh every 10s

### For System
✅ **Automatic recovery** - Optional scheduled health check
✅ **No data loss** - Jobs safely queued in database
✅ **Idempotent retries** - Jobs designed to safely re-run
✅ **Complete audit trail** - All failures logged

### For Business
✅ **Reduced downtime impact** - Quick recovery when CM is back
✅ **Better observability** - Know what's failing and why
✅ **Faster resolution** - No SSH/CLI skills needed
✅ **Proactive monitoring** - Admin notifications on recovery

---

## Database Requirements

### Existing Tables (Already Present)

```sql
-- Laravel's built-in failed jobs table
CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(255) UNIQUE,
    connection TEXT,
    queue TEXT,
    payload LONGTEXT,
    exception LONGTEXT,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Laravel's jobs table (for pending jobs)
CREATE TABLE jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255),
    payload LONGTEXT,
    attempts TINYINT UNSIGNED,
    reserved_at INT UNSIGNED NULL,
    available_at INT UNSIGNED,
    created_at INT UNSIGNED
);
```

**No new tables required!** All functionality uses Laravel's built-in queue tables.

---

## Testing Plan

### Manual Testing

1. **Simulate CM Downtime**
   ```php
   // Temporarily break CM credentials in .env
   CAMPAIGN_MONITOR_API_KEY=invalid_key_for_testing
   ```

2. **Trigger Jobs**
   - Register new user (triggers AddSubscriberToCm job)
   - Send campaign (triggers TagAndSendCampaignJob)
   - Wait for jobs to fail (3 retries = ~20 minutes)

3. **Test UI**
   - Navigate to `/admin/cdp/sync-status`
   - Verify "OFFLINE" status shows
   - Verify failed jobs appear in list
   - Click "View" on job → See details
   - Restore CM credentials
   - Click "Retry All Failed Jobs"
   - Verify jobs succeed

4. **Test Auto-Recovery** (if implemented)
   - Break CM credentials
   - Wait for jobs to fail
   - Restore CM credentials
   - Wait 5 minutes (next health check)
   - Verify auto-retry triggered
   - Verify admin notification sent

---

## Implementation Priority

### Phase 1: Essential (Week 1)
- ✅ CM Health Check action
- ✅ Sync Status Dashboard UI
- ✅ Failed jobs list with details
- ✅ Individual retry button
- ✅ Bulk "Retry All" button
- ✅ Route + navigation item

### Phase 2: Enhanced (Week 2)
- ✅ Campaign list bulk retry
- ✅ Job detail modal
- ✅ Delete/clear failed jobs
- ✅ Real-time polling (10s refresh)
- ✅ Failed job count badge in nav

### Phase 3: Advanced (Optional)
- ⏳ Auto-recovery scheduled command
- ⏳ Admin notifications on recovery
- ⏳ Historical downtime tracking
- ⏳ CM API latency graph
- ⏳ Queue performance metrics

---

## Files to Create

### New Files (Phase 1)

```
app/
  Actions/
    CampaignMonitor/
      CheckHealthAction.php
    Queue/
      RetryFailedJobAction.php
      RetryAllFailedJobsAction.php
  Livewire/
    Admin/
      SyncStatusDashboard.php

resources/
  views/
    livewire/
      admin/
        sync-status-dashboard.blade.php
```

### Modified Files (Phase 1)

```
routes/web.php (add sync-status route)
resources/views/components/layouts/app/sidebar.blade.php (badge for failed count)
```

### New Files (Phase 3 - Optional)

```
app/
  Console/
    Commands/
      MonitorCampaignMonitorHealth.php
  Notifications/
    CampaignMonitorRecoveredNotification.php
```

---

## Configuration

### Environment Variables

No new environment variables required. Uses existing:

```env
CAMPAIGN_MONITOR_API_KEY=your_api_key
CAMPAIGN_MONITOR_CLIENT_ID=your_client_id
CAMPAIGN_MONITOR_LIST_ID=your_list_id
```

### Queue Configuration

Already specified in CDP_WORKFLOWS.md. No changes needed:

```php
// config/queue.php
'connections' => [
    'database' => [
        'driver' => 'database',
        'queue' => 'default',
        'retry_after' => 90,
    ],
],
```

---

## Monitoring & Alerts

### Success Metrics

- **Failed Job Count**: Should trend to 0 after CM recovery
- **Retry Success Rate**: % of retried jobs that succeed
- **Recovery Time**: Minutes from CM recovery to 0 failed jobs
- **Admin Response Time**: Minutes from failure to manual retry

### Recommended Alerts

1. **Failed Jobs > 50**: Email admin immediately
2. **CM Offline > 30 mins**: Send urgent notification
3. **Queue Not Processing**: Alert if no jobs processed in 10 mins

---

## Security Considerations

### Access Control

```php
// Route middleware
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/cdp/sync-status', SyncStatusDashboard::class)
        ->name('admin.cdp.sync-status');
});
```

### Rate Limiting

```php
// Prevent retry spam
public function retryAllFailedJobs()
{
    if (Cache::has('retry_all_cooldown')) {
        session()->flash('error', 'Please wait 1 minute between bulk retries.');
        return;
    }

    Cache::put('retry_all_cooldown', true, now()->addMinute());

    // ... perform retry
}
```

---

## Conclusion

This specification provides a complete UI-based solution for managing Campaign Monitor downtime and queue failures. The implementation eliminates CLI dependency, improves admin experience, and ensures fast recovery from API outages.

**Key Outcome:**
Admins can monitor, diagnose, and recover from CM downtime entirely through the web interface in under 5 minutes with zero data loss.

---

**Document Status:** Ready for Implementation
**Next Steps:** Begin Phase 1 development (Sync Status Dashboard)
