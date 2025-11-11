# Campaign Monitor CDP Integration

Complete Customer Data Platform (CDP) integration with Campaign Monitor, featuring intelligent bulk tag synchronization and human-readable naming conventions.

**Key Achievement**: 99.8% reduction in API calls through intelligent batching

---

## 🎯 Overview

This integration solves Campaign Monitor's 50 custom field limitation while providing:

- ✅ **13 Core Custom Fields** - Essential user data synced to CM
- ✅ **Unlimited Tags** - Product subscriptions, segments, behaviors
- ✅ **Bulk Tag Sync** - 99.8% reduction in API calls
- ✅ **Human-Readable Everything** - CM admins understand without training
- ✅ **Complex Query Filtering** - Target any user segment
- ✅ **Real-Time Webhooks** - Track all email engagement
- ✅ **Engagement Scoring** - 0-100 score based on opens, clicks, recency
- ✅ **One-Way Sync** - Laravel as source of truth

---

## 📋 Table of Contents

1. [Quick Start](#quick-start)
2. [Architecture](#architecture)
3. [Core Features](#core-features)
4. [Usage Examples](#usage-examples)
5. [Commands](#commands)
6. [Configuration](#configuration)
7. [Webhooks](#webhooks)
8. [Testing](#testing)
9. [Deployment](#deployment)
10. [Troubleshooting](#troubleshooting)

---

## 🚀 Quick Start

### Installation

```bash
# 1. Install dependencies (already included)
composer install

# 2. Configure environment
cp .env.example .env

# Add to .env:
CM_API_KEY=your_api_key
CM_CLIENT_ID=your_client_id
CM_LIST_ID=your_list_id

# 3. Run migrations
php artisan migrate

# 4. Setup Campaign Monitor fields
php artisan cm:setup-fields

# 5. Configure cron (production)
# Add to crontab:
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### First Test

```bash
# Test tag sync with dry-run
php artisan cm:sync-tags --dry-run

# Test with real sync (small batch)
php artisan cm:sync-tags --limit=5

# Verify scheduled tasks
php artisan schedule:list
```

---

## 🏗️ Architecture

### The Core Problem

Campaign Monitor allows only **50 custom fields per list**, but we need to track:
- Multiple product subscriptions (unlimited)
- Rich engagement metrics
- Event attendance
- Organization data
- User behavior patterns

### The Solution

**13 Core Custom Fields** + **Unlimited Tags** + **Full Data in Laravel**

```
┌─────────────────────────────────────────────────────────┐
│                  Laravel Database (CDP)                  │
│  ✅ Complete user data                                   │
│  ✅ Product subscriptions (unlimited)                    │
│  ✅ Engagement metrics (detailed)                        │
│  ✅ Event attendance (full history)                      │
│  ✅ User audit logs                                      │
└─────────────────────────────────────────────────────────┘
                          ↓ Sync ↓
┌─────────────────────────────────────────────────────────┐
│              Campaign Monitor (Email Engine)             │
│  📧 13 Core Custom Fields                                │
│     - User ID, Tier, Engagement Score, etc.              │
│  🏷️ Unlimited Tags                                       │
│     - [Tier] Paid Premium                                │
│     - [Engagement] High (70-100)                         │
│     - [Product] Premium Content                          │
│     - [Event] Annual Conference 2025                     │
└─────────────────────────────────────────────────────────┘
```

### Key Design Decisions

1. **One-Way Sync** (Laravel → CM only)
2. **Mark-for-Sync Pattern** (avoids API storms)
3. **Scheduled Batch Processing** (every 10 minutes)
4. **Human-Readable Names** (CM admins don't need training)
5. **Two-Track Campaigns**:
   - Permanent tags for recurring campaigns
   - Temporary tags for one-off campaigns

---

## 🎯 Core Features

### 1. Bulk Tag Sync (99.8% API Reduction)

**Problem**: Changing organization tier for 2,847 users = 2,847 API calls

**Solution**: Mark-for-sync + scheduled batch job = 6 API calls

```php
// User tier changes
$user->update(['tier' => 'paid_premium']);
// → Marks cm_tags_need_sync = true (database only, no API call)

// Every 10 minutes, scheduled command runs:
php artisan cm:sync-tags
// → Batches all marked users (500 per batch)
// → 2,847 users = 6 API calls total!
```

### 2. Human-Readable Naming

Everything in Campaign Monitor is self-explanatory:

```php
// Custom Fields
"Engagement Score (0-100)" // not "eng_score"
"Total Email Opens"        // not "total_opens"
"Organization Name"        // not "org_name"

// Tags
"[Tier] Paid Premium"          // not "tier_pp"
"[Engagement] High (70-100)"   // not "eng_high"
"[Product] Premium Content"    // not "prod_123"

// Segments
"[Recurring] Paid Pro - Daily Digest"  // not "seg_456"
"[One-off] Event Alumni - High Engagement (247 users) [2024-11-10]"

// Campaigns
"[Event Alumni] We Miss You - Nov 10, 2024"  // not "camp_789"
```

### 3. Complex Query Filtering

Filter users with any Laravel query and tag them for campaigns:

```php
use App\Services\CampaignTagService;

$service = app(CampaignTagService::class);

// Example: Event alumni who are highly engaged
$query = User::whereHas('eventAttendances', function($q) {
    $q->where('attended_at', '>=', now()->subYears(2));
})
->where('engagement_score', '>=', 70)
->where('cm_status', 'active');

$result = $service->tagUsersFromQuery($query, 'Event Alumni 2023');

// Result:
// campaign_tag: "campaign_event-alumni-2023_20241110-143522"
// tagged: 247
// user_ids: [1, 5, 12, ...]
```

### 4. Real-Time Engagement Tracking

7 webhook types capture all email interactions:

- ✅ **Opens** - Increments total_opens, updates last_email_opened_at
- ✅ **Clicks** - Tracks URLs, increments total_clicks
- ✅ **Bounces** - Separates hard/soft, updates cm_status
- ✅ **Unsubscribes** - Updates cm_status, records timestamp
- ✅ **Spam Complaints** - Flags account
- ✅ **Updates** - Syncs status changes
- ✅ **Deactivations** - Handles composite events

### 5. Engagement Scoring (0-100)

Automatically calculated based on:

- **40 points** - Open rate (opens ÷ sent × 40)
- **40 points** - Click rate (clicks ÷ sent × 40)
- **20 points** - Recency (based on last_activity_at)
  - 0-7 days: 20 points
  - 8-30 days: 15 points
  - 31-60 days: 10 points
  - 61-90 days: 5 points
  - 90+ days: 0 points

```php
use App\Services\EngagementMetricsService;

$score = app(EngagementMetricsService::class)
    ->calculateEngagementScore($user);

// Returns: 85 (High engagement)
```

---

## 💡 Usage Examples

### Example 1: One-Off Campaign to Event Alumni

```php
use App\Services\CampaignTagService;
use App\Services\CmSyncService;
use App\Services\CmNamingService;
use App\Jobs\TagUsersBulkJob;
use App\Jobs\ClearTagBulkJob;

// 1. Build complex query
$users = User::whereHas('eventAttendances', function($q) {
    $q->whereBetween('attended_at', [
        now()->subYears(2)->startOfYear(),
        now()->subYears(2)->endOfYear()
    ])->where('status', 'attended');
})
->where('engagement_score', '>=', 70)
->where('cm_status', 'active')
->get();

// 2. Tag users in Laravel
$campaignTag = app(CampaignTagService::class);
$result = $campaignTag->tagUsersFromQuery($users, 'Event Alumni 2023');

// 3. Generate human-readable segment name
$naming = app(CmNamingService::class);
$segmentName = $naming->generateOneOffSegmentName(
    'Event Alumni',
    'High Engagement',
    $result['tagged']
);
// Result: "[One-off] Event Alumni - High Engagement (247 users) [2024-11-10]"

// 4. Sync tags to Campaign Monitor (queued)
TagUsersBulkJob::dispatch($result['user_ids'], $result['campaign_tag']);

// 5. Create segment in CM
$cmSync = app(CmSyncService::class);
$segmentId = $cmSync->createSegment([
    'Title' => $segmentName,
    'RuleGroups' => [[
        'Rules' => [[
            'Subject' => 'temp_campaign_tag',
            'Clauses' => ['EQUALS', $result['campaign_tag']]
        ]]
    ]]
]);

// 6. Send campaign via CM (or use API)
// ... create and send campaign to segment

// 7. Cleanup after send (2 hours later)
ClearTagBulkJob::dispatch($result['campaign_tag'])
    ->delay(now()->addHours(2));
```

### Example 2: Recurring Daily Digest

```php
use App\Services\CmNamingService;
use App\Services\CmSyncService;

$naming = app(CmNamingService::class);

// 1. Create permanent segment for Paid Pro users
$segmentName = $naming->generateRecurringSegmentName(
    'paid_pro',
    'daily',
    'Digest'
);
// Result: "[Recurring] Paid Pro - Daily Digest"

$segmentId = app(CmSyncService::class)->createSegment([
    'Title' => $segmentName,
    'RuleGroups' => [[
        'Rules' => [[
            'Subject' => '[Tier] Paid Pro',  // Permanent tag
            'Clauses' => ['CONTAINS']
        ]]
    ]]
]);

// 2. Create scheduled command to send daily
// app/Console/Commands/SendDailyDigest.php
class SendDailyDigest extends Command
{
    protected $signature = 'cm:send-daily-digest';

    public function handle()
    {
        $campaignName = app(CmNamingService::class)
            ->generateRecurringCampaignName('paid_pro', 'daily');

        // Create and send campaign...
    }
}

// 3. Schedule in routes/console.php
Schedule::command('cm:send-daily-digest')
    ->dailyAt('09:00');
```

### Example 3: Re-engagement Campaign

```php
use App\Services\CampaignTagService;

$service = app(CampaignTagService::class);

// Tag users who haven't been active in 90 days
$result = $service->tagDisengagedUsers(90, 'Win-Back Q4 2024');

// Returns:
// campaign_tag: "campaign_win-back-q4-2024_20241110-143522"
// tagged: 1,524
// user_ids: [...]

// Users where:
// - engagement_score < 40
// - last_activity_at < 90 days ago
// - cm_status = 'active'
```

### Example 4: Tier-Based VIP Campaign

```php
use App\Services\CampaignTagService;

$service = app(CampaignTagService::class);

// Tag highly engaged premium users
$result = $service->tagHighlyEngagedByTier(
    'paid_premium',
    80,
    'Premium VIP Campaign'
);

// Users where:
// - tier = 'paid_premium'
// - engagement_score >= 80
// - cm_status = 'active'
```

---

## 🔧 Commands

### Tag Sync

```bash
# Sync all users needing tag updates
php artisan cm:sync-tags

# Test with dry-run (no actual changes)
php artisan cm:sync-tags --dry-run

# Limit to first 100 users
php artisan cm:sync-tags --limit=100

# Test with very small batch
php artisan cm:sync-tags --limit=5 --dry-run
```

### Setup

```bash
# Create 13 core custom fields in Campaign Monitor
php artisan cm:setup-fields

# Force recreate fields (if they exist)
php artisan cm:setup-fields --force
```

### Scheduler

```bash
# List all scheduled tasks
php artisan schedule:list

# Run all scheduled tasks manually (for testing)
php artisan schedule:run

# Run scheduler continuously (development)
php artisan schedule:work
```

---

## ⚙️ Configuration

### Environment Variables

```bash
# Required
CM_API_KEY=your_api_key_here
CM_CLIENT_ID=your_client_id_here
CM_LIST_ID=your_list_id_here

# Optional
CM_SYNC_THRESHOLD=10              # User count for immediate vs queued sync
CM_BULK_IMPORT_BATCH_SIZE=1000    # Batch size for bulk imports
CM_DISABLE_AUTO_SYNC=false        # Disable automatic sync (testing)
CM_SEGMENT_CLEANUP_DAYS=30        # Days to keep one-off segments
```

### Config File

**Location**: `config/campaign-monitor.php`

Key sections:
- `core_fields` - 13 custom fields with human-readable names
- `tag_categories` - Tag prefixes (`[Tier]`, `[Engagement]`, etc.)
- `tier_names` - Human-readable tier names
- `engagement_levels` - Engagement score ranges
- `campaign_name_templates` - Template formats

---

## 📬 Webhooks

### Setup in Campaign Monitor

Configure these webhook URLs:

```
https://yourapp.com/webhooks/cm/open
https://yourapp.com/webhooks/cm/click
https://yourapp.com/webhooks/cm/bounce
https://yourapp.com/webhooks/cm/unsubscribe
https://yourapp.com/webhooks/cm/spam-complaint
https://yourapp.com/webhooks/cm/update
https://yourapp.com/webhooks/cm/deactivate
```

### What They Do

| Webhook | Updates |
|---------|---------|
| **open** | `total_opens++`, `last_email_opened_at`, `last_activity_at` |
| **click** | `total_clicks++`, `last_email_clicked_at`, `last_activity_at`, stores URL |
| **bounce** | `total_bounces++`, `cm_status = 'bounced'` (if hard), stores reason |
| **unsubscribe** | `cm_status = 'unsubscribed'`, `cm_unsubscribed_at` |
| **spam-complaint** | `cm_status = 'spam_complaint'` |
| **update** | Syncs `cm_status` changes from CM |
| **deactivate** | Handles multiple events in one webhook |

---

## 🧪 Testing

### Unit Tests

```bash
# Run all tests
vendor/bin/pest

# Run specific test file
vendor/bin/pest tests/Feature/Services/CampaignTagServiceTest.php

# Run with coverage
vendor/bin/pest --coverage
```

### Manual Testing

```bash
# 1. Test tag sync
php artisan tinker
>>> $user = \App\Models\User::first();
>>> $user->update(['tier' => 'paid_premium']);
>>> $user->cm_tags_need_sync
=> true

>>> exit();
php artisan cm:sync-tags --limit=1
>>> $user->refresh();
>>> $user->cm_tags_need_sync
=> false

# 2. Test organization tier change
php artisan tinker
>>> $org = \App\Models\Organization::has('users')->first();
>>> $org->update(['tier' => 'enterprise']);
>>> $org->users()->where('cm_tags_need_sync', true)->count()
=> 2847  # All users marked!
```

---

## 🚀 Deployment

See [DEPLOYMENT.md](../DEPLOYMENT.md) for complete deployment guide.

**Quick checklist**:

- [ ] Configure environment variables
- [ ] Run migrations
- [ ] Setup CM fields
- [ ] Configure cron
- [ ] Test tag sync
- [ ] Monitor first runs

---

## 🐛 Troubleshooting

### Users not being marked for sync

**Check**:
1. Is `cm_tags_need_sync` in User model `$fillable`?
2. Is UserObserver registered in AppServiceProvider?
3. Run: `php artisan optimize:clear`

### Scheduled task not running

**Check**:
1. Is cron configured? `crontab -l`
2. Is Laravel scheduler seeing the task? `php artisan schedule:list`
3. Run manually: `php artisan schedule:run`

### Sync command fails

**Check**:
1. Are CM API credentials correct?
2. Test connection in tinker
3. Check Campaign Monitor API status
4. Review error logs

---

## 📚 Documentation

| Document | Purpose |
|----------|---------|
| [50-field-limit-solution.md](./50-field-limit-solution.md) | Core problem & solution |
| [bulk-tag-sync-strategy.md](./bulk-tag-sync-strategy.md) | API storm prevention |
| [human-readable-cm-strategy.md](./human-readable-cm-strategy.md) | Naming conventions |
| [complex-query-capabilities.md](./complex-query-capabilities.md) | Campaign filtering |
| [webhook-implementation-summary.md](./webhook-implementation-summary.md) | Webhook details |
| [test-coverage-summary.md](./test-coverage-summary.md) | Test documentation |
| [DEPLOYMENT.md](../DEPLOYMENT.md) | Deployment guide |

---

## 📊 Statistics

- **Total Code**: ~13,000 lines
- **Test Coverage**: 2,600+ lines, 164+ test cases
- **Documentation**: 11 comprehensive guides
- **API Efficiency**: 99.8% reduction for bulk operations
- **Production Status**: ✅ Ready

---

## 🎖️ Key Achievements

✅ **50-Field Limit Solved** - 13 core fields + unlimited tags
✅ **API Storms Prevented** - 99.8% reduction through batching
✅ **Human-Readable** - CM admins need no training
✅ **Complex Queries** - Filter by any criteria
✅ **Real-Time Engagement** - 7 webhook types
✅ **Automated Scoring** - 0-100 engagement calculation
✅ **Production Ready** - Comprehensive testing & documentation

---

*Last Updated: November 11, 2024*
*Version: 1.0*
*Status: Production Ready ✅*
