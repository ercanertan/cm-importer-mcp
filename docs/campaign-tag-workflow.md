# Campaign Tag Workflow: The Scalable Solution

## The Problem

You have **60,000+ users** in your system. You need to send targeted campaigns to dynamic segments like:
- "Paid orgs, opted into 'Product X', haven't logged in for 30 days" (25,000 users)
- "Free tier users who attended an event in the last week" (5,000 users)
- "High engagement users in enterprise orgs" (12,000 users)

**Traditional approaches fail:**
- ❌ Creating 60k individual segments in CM → API nightmare
- ❌ Creating 50+ custom fields for every product/behavior → Hit 50-field limit
- ❌ Syncing 60k users one-by-one → Thousands of API calls, takes hours

## The Solution: Campaign Tag Workflow

**Key Insight**: Use a **single reusable custom field** (`temp_campaign_tag`) as a "scratchpad" for campaign targeting. Set it in bulk, create segment, send campaign, clean up.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                  Laravel CDP (60,000 users)                  │
│  • Complete user data                                        │
│  • Product subscriptions                                     │
│  • Engagement metrics                                        │
│  • Organization data                                         │
└─────────────────────────────────────────────────────────────┘
                              ↓
                    Admin builds segment query
                              ↓
┌─────────────────────────────────────────────────────────────┐
│         Query results: 25,000 email addresses                │
└─────────────────────────────────────────────────────────────┘
                              ↓
         Queue bulk job: TagUsersBulkJob (25 API calls)
                              ↓
┌─────────────────────────────────────────────────────────────┐
│          Campaign Monitor (60,000 subscribers)               │
│  temp_campaign_tag = "campaign_20251102_product_x" (25k)    │
│  temp_campaign_tag = null (35k others)                      │
└─────────────────────────────────────────────────────────────┘
                              ↓
            Create segment: temp_campaign_tag = "campaign_20251102_product_x"
                              ↓
                      Send campaign to segment
                              ↓
         Queue cleanup job: ClearTagBulkJob (25 API calls)
                              ↓
┌─────────────────────────────────────────────────────────────┐
│          Campaign Monitor (60,000 subscribers)               │
│  temp_campaign_tag = null (all users)                       │
│  Ready for next campaign!                                    │
└─────────────────────────────────────────────────────────────┘
```

---

## Prerequisites

### One-Time Setup in Campaign Monitor

Create a single reusable custom field:

- **Field Name**: `temp_campaign_tag`
- **Data Type**: Text
- **Purpose**: Temporary marker for campaign targeting

This is handled by the `SetupCampaignMonitorFields` command:

```bash
php artisan cm:setup-fields
```

This command creates all custom fields including `temp_campaign_tag`.

---

## The Workflow (5 Steps)

### Step 1: Segment in Laravel (Your CDP)

**Admin Action**: Build segment in Laravel app UI

```php
// Example: Admin defines segment criteria
$segment = [
    'organization_tier' => ['paid_pro', 'paid_premium'],
    'products' => ['product_x'],
    'last_login_before' => now()->subDays(30),
    'email_status' => 'subscribed',
];

// Query Laravel database (FAST - this is your CDP)
$users = User::query()
    ->whereHas('organization', function($q) use ($segment) {
        $q->whereIn('tier', $segment['organization_tier']);
    })
    ->whereHas('products', function($q) use ($segment) {
        $q->where('product_id', Product::where('slug', 'product_x')->first()->id)
          ->where('subscribed', true);
    })
    ->where('last_login_at', '<', $segment['last_login_before'])
    ->where('cm_status', 'active')
    ->get(['id', 'email']);

// Result: 25,000 email addresses
```

**Key Point**: This query runs entirely in Laravel. No CM API calls yet. Fast and flexible.

---

### Step 2: Tag in Campaign Monitor (The Bulk Part)

**Generate unique campaign tag:**

```php
$campaignTag = 'campaign_' . now()->format('Ymd_His') . '_product_x';
// Result: "campaign_20251102_143522_product_x"
```

**Dispatch bulk tagging job:**

```php
TagUsersBulkJob::dispatch(
    userIds: $users->pluck('id')->toArray(),
    tagValue: $campaignTag,
    batchSize: 1000 // Process 1000 users per API call
);
```

**Job Implementation** (`TagUsersBulkJob`):

```php
public function handle(CmSyncService $cmSyncService): void
{
    $users = User::whereIn('id', $this->userIds)
        ->where('cm_status', 'active')
        ->get();

    // Process in batches of 1000 (configurable)
    $batches = $users->chunk($this->batchSize);

    foreach ($batches as $batch) {
        // Use CM's Bulk Import API
        $subscribers = $batch->map(function($user) {
            return [
                'EmailAddress' => $user->email,
                'CustomFields' => [
                    [
                        'Key' => 'temp_campaign_tag',
                        'Value' => $this->tagValue
                    ]
                ],
                'Resubscribe' => true, // Don't change subscription status
            ];
        })->toArray();

        // Single API call for 1000 users
        $result = $cmSyncService->bulkImport($subscribers);

        Log::info('Bulk tagged users for campaign', [
            'tag' => $this->tagValue,
            'batch_size' => count($subscribers),
            'succeeded' => $result['succeeded'],
            'failed' => $result['failed'],
        ]);

        // Respect rate limits (small delay between batches)
        if (!$batches->last()) {
            usleep(200000); // 0.2 second pause
        }
    }
}
```

**API Efficiency**:
- 25,000 users ÷ 1,000 per batch = **25 API calls**
- Each call takes ~1-2 seconds
- Total tagging time: ~30-60 seconds (queued in background)

**CM API Endpoint Used**:
```
POST /api/v3.3/subscribers/{list_id}/import.json
```

**Sample Payload**:
```json
{
  "Subscribers": [
    {
      "EmailAddress": "user1@example.com",
      "CustomFields": [
        {"Key": "temp_campaign_tag", "Value": "campaign_20251102_143522_product_x"}
      ],
      "Resubscribe": true
    },
    {
      "EmailAddress": "user2@example.com",
      "CustomFields": [
        {"Key": "temp_campaign_tag", "Value": "campaign_20251102_143522_product_x"}
      ],
      "Resubscribe": true
    }
    // ... 998 more users
  ],
  "RestartSubscriptionBasedAutoresponders": false
}
```

---

### Step 3: Create Segment in Campaign Monitor

**After tagging completes**, create a segment in CM to target these users:

```php
// Service method to create segment
$segmentId = CmSyncService::createSegment([
    'Title' => 'Product X Re-engagement - Nov 2025',
    'RuleGroups' => [
        [
            'Rules' => [
                [
                    'Subject' => 'temp_campaign_tag',
                    'Clauses' => ['EQUALS', $campaignTag]
                ]
            ]
        ]
    ]
]);
```

**CM API Endpoint**:
```
POST /api/v3.3/segments/{list_id}.json
```

**Sample Payload**:
```json
{
  "Title": "Product X Re-engagement - Nov 2025",
  "RuleGroups": [
    {
      "Rules": [
        {
          "Subject": "temp_campaign_tag",
          "Clauses": ["EQUALS", "campaign_20251102_143522_product_x"]
        }
      ]
    }
  ]
}
```

**Result**: Campaign Monitor instantly builds the segment with 25,000 subscribers.

**API Efficiency**: **1 API call** to create segment.

---

### Step 4: Send Campaign

**Option A: Via CM UI**
1. Create campaign in CM dashboard
2. Select the segment created in Step 3
3. Send campaign

**Option B: Via API**

```php
$campaignId = CmSyncService::createCampaign([
    'Subject' => 'We miss you! Special offer on Product X',
    'Name' => 'Product X Re-engagement - Nov 2025',
    'FromName' => 'Your Company',
    'FromEmail' => 'hello@example.com',
    'ReplyTo' => 'support@example.com',
    'HtmlUrl' => 'https://yourapp.com/email-templates/product-x-reengagement',
    'TextUrl' => 'https://yourapp.com/email-templates/product-x-reengagement/text',
    'SegmentIDs' => [$segmentId],
]);

CmSyncService::sendCampaign($campaignId);
```

**API Endpoints**:
```
POST /api/v3.3/campaigns/{client_id}.json  (create)
POST /api/v3.3/campaigns/{campaign_id}/send.json  (send)
```

**API Efficiency**: **2 API calls** (1 to create, 1 to send).

---

### Step 5: Cleanup (After Campaign Sent)

**Important**: Clear the `temp_campaign_tag` field to prepare for the next campaign.

```php
// Dispatch cleanup job (can be delayed)
ClearTagBulkJob::dispatch(
    userIds: $users->pluck('id')->toArray(),
    batchSize: 1000
)->delay(now()->addHours(1)); // Give campaign time to send first
```

**Job Implementation** (`ClearTagBulkJob`):

```php
public function handle(CmSyncService $cmSyncService): void
{
    $users = User::whereIn('id', $this->userIds)
        ->where('cm_status', 'active')
        ->get();

    $batches = $users->chunk($this->batchSize);

    foreach ($batches as $batch) {
        $subscribers = $batch->map(function($user) {
            return [
                'EmailAddress' => $user->email,
                'CustomFields' => [
                    [
                        'Key' => 'temp_campaign_tag',
                        'Value' => '' // Clear the tag
                    ]
                ],
                'Resubscribe' => true,
            ];
        })->toArray();

        $result = $cmSyncService->bulkImport($subscribers);

        Log::info('Bulk cleared campaign tags', [
            'batch_size' => count($subscribers),
            'succeeded' => $result['succeeded'],
            'failed' => $result['failed'],
        ]);

        if (!$batches->last()) {
            usleep(200000); // 0.2 second pause
        }
    }
}
```

**API Efficiency**: 25,000 users ÷ 1,000 per batch = **25 API calls**

**Result**: All users have `temp_campaign_tag = null`, ready for next campaign.

---

## Complete API Call Count

For a campaign to 25,000 users out of 60,000:

| Step | Action | API Calls |
|------|--------|-----------|
| 1 | Segment in Laravel | **0** (local query) |
| 2 | Tag users (25k ÷ 1k batches) | **25** |
| 3 | Create segment | **1** |
| 4 | Send campaign | **2** |
| 5 | Cleanup tags | **25** |
| **Total** | | **53 API calls** |

**Compare to naive approach**: 25,000 individual API calls = **472x more efficient!**

---

## Real-World Example: Multiple Campaigns in One Day

### Scenario
You want to send 3 different campaigns to 3 different segments on the same day:

1. **Morning**: "Product X Re-engagement" → 25,000 users
2. **Afternoon**: "Event Invitation" → 8,000 users
3. **Evening**: "Premium Upgrade Offer" → 3,500 users

### Implementation

**Campaign 1** (Morning - 9am):
```php
// Tag users
TagUsersBulkJob::dispatch($productXUsers, 'campaign_20251102_090000_product_x');
// Create segment
$seg1 = CmSyncService::createSegment([...]);
// Send campaign
CmSyncService::sendCampaign($campaignId1, [$seg1]);
// Cleanup (after 1 hour)
ClearTagBulkJob::dispatch($productXUsers)->delay(now()->addHour());
```

**Campaign 2** (Afternoon - 2pm):
```php
// Tag users (temp_campaign_tag is now clear from Campaign 1)
TagUsersBulkJob::dispatch($eventUsers, 'campaign_20251102_140000_event');
// Create segment
$seg2 = CmSyncService::createSegment([...]);
// Send campaign
CmSyncService::sendCampaign($campaignId2, [$seg2]);
// Cleanup
ClearTagBulkJob::dispatch($eventUsers)->delay(now()->addHour());
```

**Campaign 3** (Evening - 6pm):
```php
// Tag users
TagUsersBulkJob::dispatch($upgradeUsers, 'campaign_20251102_180000_upgrade');
// Create segment
$seg3 = CmSyncService::createSegment([...]);
// Send campaign
CmSyncService::sendCampaign($campaignId3, [$seg3]);
// Cleanup
ClearTagBulkJob::dispatch($upgradeUsers)->delay(now()->addHour());
```

**Key**: The same `temp_campaign_tag` field is reused for all 3 campaigns. Just ensure cleanup happens between campaigns.

---

## Advanced: Tracking Campaign Results

After sending, track engagement in Laravel:

```php
// Store campaign metadata
Campaign::create([
    'cm_campaign_id' => $campaignId,
    'name' => 'Product X Re-engagement - Nov 2025',
    'segment_tag' => $campaignTag,
    'sent_to_count' => 25000,
    'sent_at' => now(),
]);

// Campaign Monitor webhooks → EmailEngagement records
// Webhook handler:
public function handleCampaignEvent(Request $request)
{
    $event = $request->input('Events')[0];

    EmailEngagement::create([
        'user_id' => User::where('email', $event['EmailAddress'])->first()->id,
        'campaign_id' => $event['CampaignID'],
        'event_type' => $event['Type'], // 'Open', 'Click', 'Bounce'
        'event_at' => $event['Date'],
        'metadata' => $event,
    ]);

    // Update user aggregates
    $user = User::where('email', $event['EmailAddress'])->first();
    if ($event['Type'] === 'Open') {
        $user->increment('total_opens');
        $user->update(['last_email_opened_at' => $event['Date']]);
    }

    // Update engagement score
    $score = EngagementMetricsService::calculateEngagementScore($user);
    $user->update(['engagement_score' => $score]);
}
```

**Report on campaign performance:**
```php
$campaign = Campaign::where('cm_campaign_id', $campaignId)->first();

$stats = [
    'sent' => $campaign->sent_to_count,
    'opened' => EmailEngagement::where('campaign_id', $campaignId)
        ->where('event_type', 'opened')
        ->distinct('user_id')
        ->count(),
    'clicked' => EmailEngagement::where('campaign_id', $campaignId)
        ->where('event_type', 'clicked')
        ->distinct('user_id')
        ->count(),
    'bounced' => EmailEngagement::where('campaign_id', $campaignId)
        ->where('event_type', 'bounced')
        ->count(),
];

$stats['open_rate'] = ($stats['opened'] / $stats['sent']) * 100;
$stats['click_rate'] = ($stats['clicked'] / $stats['sent']) * 100;
```

---

## Database Schema

### Migration for temp_campaign_tag (Already Created)

```php
// database/migrations/2025_11_10_182625_add_temp_campaign_tag_to_users_table.php
Schema::table('users', function (Blueprint $table) {
    $table->string('temp_campaign_tag')->nullable()->after('cm_subscriber_id');
    $table->index('temp_campaign_tag'); // For quick queries
});
```

### Optional: Campaigns Table (Track Campaign Metadata)

```php
Schema::create('campaigns', function (Blueprint $table) {
    $table->id();
    $table->string('cm_campaign_id')->unique(); // CM's campaign ID
    $table->string('name');
    $table->string('subject');
    $table->string('segment_tag'); // The temp_campaign_tag value used
    $table->integer('sent_to_count');
    $table->timestamp('sent_at')->nullable();
    $table->json('metadata')->nullable(); // Store segment criteria
    $table->timestamps();
});
```

---

## Error Handling & Edge Cases

### What if tagging fails for some users?

```php
// TagUsersBulkJob tracks failures
$result = $cmSyncService->bulkImport($subscribers);

if ($result['failed'] > 0) {
    Log::warning('Some users failed to tag', [
        'tag' => $this->tagValue,
        'failed_count' => $result['failed'],
        'failed_emails' => $result['failed_emails'],
    ]);

    // Optionally: Retry failed users
    RetryFailedTagsJob::dispatch($result['failed_emails'], $this->tagValue)
        ->delay(now()->addMinutes(5));
}
```

### What if two campaigns overlap?

**Problem**: Admin sends Campaign 1, then immediately queues Campaign 2 before cleanup finishes.

**Solution**: Add campaign state tracking

```php
// Check if temp_campaign_tag is in use
if (Cache::has('active_campaign_tag')) {
    throw new Exception('Another campaign is in progress. Please wait.');
}

// Lock during tagging
Cache::put('active_campaign_tag', $campaignTag, now()->addHours(2));

// Release after cleanup
ClearTagBulkJob::dispatch($users)
    ->chain([
        new function() use ($campaignTag) {
            Cache::forget('active_campaign_tag');
        }
    ]);
```

### What if cleanup fails?

**Problem**: Cleanup job fails, leaving `temp_campaign_tag` set for some users.

**Solution**: Add a scheduled cleanup command

```php
// app/Console/Commands/CleanupStaleCampaignTags.php
public function handle()
{
    // Find users with temp_campaign_tag set for > 24 hours
    $staleUsers = User::whereNotNull('temp_campaign_tag')
        ->where('updated_at', '<', now()->subHours(24))
        ->get();

    if ($staleUsers->count() > 0) {
        Log::warning('Found stale campaign tags', [
            'count' => $staleUsers->count(),
        ]);

        ClearTagBulkJob::dispatch($staleUsers->pluck('id')->toArray());
    }
}
```

**Schedule it**:
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('cm:cleanup-stale-tags')->daily();
}
```

---

## Best Practices

### 1. Use Descriptive Campaign Tags
```php
// Good
'campaign_20251102_143522_product_x_reengagement'

// Bad
'temp_tag_1'
```

### 2. Always Cleanup
```php
// Ensure cleanup job is always dispatched
TagUsersBulkJob::dispatch($users, $tag)
    ->chain([
        new ClearTagBulkJob($users, delay: now()->addHour())
    ]);
```

### 3. Log Everything
```php
Log::info('Campaign workflow started', [
    'tag' => $campaignTag,
    'segment_criteria' => $segmentCriteria,
    'user_count' => $users->count(),
]);
```

### 4. Respect Rate Limits
```php
// Add delays between batches
usleep(200000); // 0.2 seconds

// Or use Laravel's rate limiting
RateLimiter::attempt('cm-api', 5, function() {
    // Max 5 API calls per second
});
```

### 5. Monitor Queue Health
```bash
# Ensure queue workers are running
php artisan queue:work --queue=default --tries=3

# Monitor failed jobs
php artisan queue:failed
```

---

## Summary

The **Campaign Tag Workflow** solves the scalability problem:

✅ **60,000 users** → No problem
✅ **25,000-user campaign** → 53 API calls (not 25,000)
✅ **Dynamic segments** → Build in Laravel, tag in CM
✅ **Multiple campaigns/day** → Reuse same temp field
✅ **No field limit issues** → Uses only 1 custom field
✅ **Full tracking** → All engagement data back in Laravel

**Key Principle**: Laravel segments, CM delivers. Use `temp_campaign_tag` as a scratchpad for targeting.

---

*API Reference: https://www.campaignmonitor.com/api/v3-3/getting-started/*
