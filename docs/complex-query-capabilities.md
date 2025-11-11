# Complex Query Capabilities - FULLY IMPLEMENTED ✅

## Overview

**YES!** Complex query filtering for campaigns has been fully implemented in the previous session. You can filter users with **ANY Laravel query** and tag them for campaigns.

---

## Core Method: `tagUsersFromQuery()`

**File**: `app/Services/CampaignTagService.php`
**Line**: 189

### How It Works

```php
public function tagUsersFromQuery($query, string $campaignName): array
```

**Accepts**: Any Laravel Eloquent query builder
**Returns**: Array with campaign_tag, tagged count, and user_ids

### Example: Your Exact Use Case

**"Users who attended event 2 years ago AND are highly active"**

```php
use App\Services\CampaignTagService;

$campaignTag = app(CampaignTagService::class);

// Build complex query
$query = User::whereHas('eventAttendances', function($q) {
    $q->where('attended_at', '>=', now()->subYears(2))
      ->where('attended_at', '<=', now()->subYears(2)->addYear())
      ->where('status', 'attended');
})
->where('engagement_score', '>=', 70) // Highly active = engagement score >= 70
->where('cm_status', 'active');

// Tag them for campaign
$result = $campaignTag->tagUsersFromQuery($query, 'Event Alumni Re-engagement');

// Result:
// [
//   'campaign_tag' => 'campaign_event-alumni-reengagement_20251110-143522',
//   'tagged' => 247,  // Number of users tagged
//   'user_ids' => [1, 5, 12, ...] // Array of user IDs
// ]

// Now sync to Campaign Monitor
TagUsersBulkJob::dispatch($result['user_ids'], $result['campaign_tag']);

// Create segment in CM
$segmentId = app(CmSyncService::class)->createCampaignTagSegment(
    $result['campaign_tag'],
    'Event Alumni (2 years ago) - Highly Active'
);

// Send campaign
// ... create and send campaign to segment
```

---

## Pre-Built Helper Methods (Common Use Cases)

The service includes **3 helper methods** for common complex queries:

### 1. Tag Highly Engaged Users by Tier

**Method**: `tagHighlyEngagedByTier()`

```php
/**
 * Tag highly engaged users from a specific tier
 *
 * @param string $tier - 'paid_pro', 'paid_premium', 'enterprise'
 * @param float $minEngagementScore - e.g., 70.0
 * @param string $campaignName
 */
public function tagHighlyEngagedByTier(
    string $tier,
    float $minEngagementScore,
    string $campaignName
): array

// Example: Target paid_premium users with high engagement
$result = $campaignTag->tagHighlyEngagedByTier(
    'paid_premium',
    80.0,
    'Premium VIP Campaign'
);
// Tags users where:
// - tier = 'paid_premium'
// - engagement_score >= 80
// - cm_status = 'active'
```

**Tested**: ✅ Yes (`CampaignTagServiceTest.php:265`)

---

### 2. Tag Event Attendees Who Are Active

**Method**: `tagEventAttendeesActive()`

```php
/**
 * Tag users who attended specific event and are recently active
 *
 * @param int $eventId - Event ID
 * @param int $minDaysSinceActivity - Max days since last activity
 * @param string $campaignName
 */
public function tagEventAttendeesActive(
    int $eventId,
    int $minDaysSinceActivity,
    string $campaignName
): array

// Example: Target attendees from annual conference who were active in last 30 days
$annualConferenceId = 15;

$result = $campaignTag->tagEventAttendeesActive(
    $annualConferenceId,
    30,
    'Conference Alumni Follow-up'
);
// Tags users where:
// - Attended event ID 15
// - attendance_confirmed = true
// - last_activity_at >= now()->subDays(30)
// - cm_status = 'active'
```

**Tested**: ✅ Yes (`CampaignTagServiceTest.php:294`)

---

### 3. Tag Disengaged Users for Re-engagement

**Method**: `tagDisengagedUsers()`

```php
/**
 * Tag disengaged users for re-engagement campaign
 *
 * @param int $inactiveDays - Days of inactivity
 * @param string $campaignName
 */
public function tagDisengagedUsers(
    int $inactiveDays,
    string $campaignName
): array

// Example: Target users who haven't been active in 90 days
$result = $campaignTag->tagDisengagedUsers(
    90,
    'Win-Back Campaign Q4'
);
// Tags users where:
// - engagement_score < 40 (low engagement)
// - last_activity_at < now()->subDays(90)
// - cm_status = 'active'
// - last_activity_at IS NOT NULL
```

**Tested**: ✅ Yes (`CampaignTagServiceTest.php:328`)

---

## Real-World Complex Query Examples

### Example 1: Event Alumni + High Engagement (Your Use Case)

```php
// Users who attended ANY event 2 years ago AND are highly engaged now

$query = User::whereHas('eventAttendances', function($q) {
    $q->whereBetween('attended_at', [
        now()->subYears(2)->startOfYear(),
        now()->subYears(2)->endOfYear()
    ])
    ->where('status', 'attended');
})
->where('engagement_score', '>=', 70)
->where('cm_status', 'active');

$result = app(CampaignTagService::class)
    ->tagUsersFromQuery($query, '2-Year Event Alumni VIP');
```

### Example 2: Premium Tier + Multiple Product Subscriptions + Recent Activity

```php
// Paid premium users who are subscribed to 3+ products and active in last week

$query = User::where('tier', 'paid_premium')
    ->whereHas('productSubscriptions', function($q) {
        $q->where('subscribed', true);
    }, '>=', 3) // At least 3 product subscriptions
    ->where('last_activity_at', '>=', now()->subWeek())
    ->where('total_opens', '>=', 5) // Opened at least 5 emails
    ->where('cm_status', 'active');

$result = app(CampaignTagService::class)
    ->tagUsersFromQuery($query, 'Power Users Campaign');
```

### Example 3: Attended Specific Event + Clicked Specific Email Link + In Specific Org

```php
// Users who:
// - Attended webinar ID 42
// - Clicked on pricing link in last campaign
// - Work for enterprise organizations

$query = User::whereHas('eventAttendances', function($q) {
    $q->where('event_id', 42)
      ->where('status', 'attended');
})
->whereHas('emailEngagements', function($q) {
    $q->where('event_type', 'click')
      ->where('url', 'LIKE', '%/pricing%')
      ->where('occurred_at', '>=', now()->subMonth());
})
->whereHas('organization', function($q) {
    $q->where('tier', 'enterprise');
})
->where('cm_status', 'active');

$result = app(CampaignTagService::class)
    ->tagUsersFromQuery($query, 'Webinar + Pricing Interest');
```

### Example 4: Lapsed Users from High-Value Orgs

```php
// Users who:
// - Haven't logged in for 60 days
// - Are in paid organizations
// - Previously had high engagement (>60)
// - Haven't unsubscribed

$query = User::where('last_login_at', '<', now()->subDays(60))
    ->whereHas('organization', function($q) {
        $q->whereIn('tier', ['paid_pro', 'paid_premium', 'enterprise']);
    })
    ->where('engagement_score', '>=', 60)
    ->where('cm_status', 'active')
    ->whereNull('cm_unsubscribed_at');

$result = app(CampaignTagService::class)
    ->tagUsersFromQuery($query, 'Lapsed High-Value Win-Back');
```

### Example 5: Multi-Criteria Birthday Campaign

```php
// Users who:
// - Have birthday this month
// - Are paid tier
// - Active in last 30 days
// - Opened at least 1 email this year

$query = User::whereMonth('date_of_birth', now()->month)
    ->whereIn('tier', ['paid_pro', 'paid_premium', 'enterprise'])
    ->where('last_activity_at', '>=', now()->subDays(30))
    ->where('total_opens', '>=', 1)
    ->whereYear('last_email_opened_at', now()->year)
    ->where('cm_status', 'active');

$result = app(CampaignTagService::class)
    ->tagUsersFromQuery($query, 'Birthday Campaign - Active Paid Users');
```

---

## Additional Utilities

### Get Tag Statistics

```php
$stats = app(CampaignTagService::class)
    ->getTagStats('campaign_event-alumni-reengagement_20251110-143522');

// Returns:
// [
//   'campaign_tag' => 'campaign_event-alumni-reengagement_20251110-143522',
//   'total_users' => 247,
//   'by_tier' => [
//       'paid_pro' => 120,
//       'paid_premium' => 100,
//       'enterprise' => 27
//   ],
//   'by_cm_status' => [
//       'active' => 247
//   ],
//   'avg_engagement_score' => 73.5,
//   'organizations' => 87  // Number of unique organizations
// ]
```

### Get All Active Campaign Tags

```php
$activeTags = app(CampaignTagService::class)->getActiveTags();
// Returns collection of all campaign tags currently in use
// e.g., ['campaign_a_20251110-120000', 'campaign_b_20251110-121500']

$allStats = app(CampaignTagService::class)->getAllTagsStats();
// Returns stats for ALL active campaign tags
```

---

## Complete Workflow Example

### Use Case: "Users who attended event 2 years ago and are highly active"

```php
use App\Services\CampaignTagService;
use App\Services\CmSyncService;
use App\Jobs\TagUsersBulkJob;
use App\Jobs\ClearTagBulkJob;

// Step 1: Build complex query
$query = User::whereHas('eventAttendances', function($q) {
    $q->whereBetween('attended_at', [
        now()->subYears(2)->startOfYear(),
        now()->subYears(2)->endOfYear()
    ])
    ->where('status', 'attended');
})
->where('engagement_score', '>=', 70)
->where('cm_status', 'active');

// Step 2: Tag users in Laravel database
$campaignTagService = app(CampaignTagService::class);
$result = $campaignTagService->tagUsersFromQuery(
    $query,
    'Event Alumni 2023 - High Engagement'
);

// Step 3: Get statistics (optional)
$stats = $campaignTagService->getTagStats($result['campaign_tag']);
Log::info('Campaign targeting', $stats);

// Step 4: Sync tags to Campaign Monitor (bulk job)
TagUsersBulkJob::dispatch(
    $result['user_ids'],
    $result['campaign_tag']
);

// Step 5: Create segment in CM
$cmSyncService = app(CmSyncService::class);
$segmentId = $cmSyncService->createCampaignTagSegment(
    $result['campaign_tag'],
    'Event Alumni 2023 - High Engagement (247 users)'
);

// Step 6: Send campaign (via CM API or UI)
$campaignId = $cmSyncService->createCampaign([
    'Subject' => 'We miss you! Special offer for event alumni',
    'Name' => 'Event Alumni 2023 Campaign',
    'FromName' => 'Your Team',
    'FromEmail' => 'team@yourapp.com',
    'ReplyTo' => 'support@yourapp.com',
    'HtmlUrl' => route('email.templates.event-alumni'),
    'SegmentIDs' => [$segmentId],
]);

// Step 7: Cleanup after campaign sent (delayed)
ClearTagBulkJob::dispatch($result['campaign_tag'])
    ->delay(now()->addHours(2)); // Clean up 2 hours after send

// Step 8: Delete temporary segment (optional)
// $cmSyncService->deleteSegment($segmentId);
```

---

## Test Coverage

All complex query capabilities are **fully tested**:

- ✅ `tagUsersFromQuery()` with various queries
- ✅ `tagHighlyEngagedByTier()` - filters correctly by tier + engagement
- ✅ `tagEventAttendeesActive()` - filters event attendees by activity
- ✅ `tagDisengagedUsers()` - identifies disengaged users
- ✅ Tag statistics generation
- ✅ Active tag listing
- ✅ Edge cases (no matches, empty queries)

**Test File**: `tests/Feature/Services/CampaignTagServiceTest.php`
**Lines**: 387 (comprehensive)

---

## Available Query Relationships

Based on the models, you can query on:

### User Relationships
- `eventAttendances` - Events the user attended
- `emailEngagements` - Email opens, clicks, bounces
- `organization` / `organizations` - User's organization(s)
- `productSubscriptions` - Subscribed products (if implemented)

### User Fields
- `tier` - User tier (free, paid_pro, paid_premium, enterprise)
- `engagement_score` - 0-100 score
- `cm_status` - active, unsubscribed, bounced
- `last_activity_at` - Last activity timestamp
- `last_login_at` - Last login timestamp
- `last_email_opened_at` - Last email open
- `last_email_clicked_at` - Last email click
- `total_opens` - Total email opens
- `total_clicks` - Total email clicks
- `total_bounces` - Total email bounces
- `date_of_birth` - User birthday
- `created_at` - Registration date

### Event Relationships
- `event.type` - conference, webinar, workshop, etc.
- `event.starts_at` - Event date
- `event.organizer` - Event organizer

### EventAttendance Fields
- `status` - registered, attended, no-show
- `attended_at` - Actual attendance timestamp
- `attendance_confirmed` - Boolean

### EmailEngagement Fields
- `event_type` - open, click, bounce, unsubscribe
- `campaign_id` - Which campaign
- `occurred_at` - When it happened
- `url` - For clicks, which URL

---

## Summary

**YES! Complex query filtering is FULLY implemented.**

You can:
- ✅ Build ANY Laravel query
- ✅ Tag users with `tagUsersFromQuery()`
- ✅ Use pre-built helpers for common scenarios
- ✅ Get statistics on tagged users
- ✅ Sync to Campaign Monitor in bulk
- ✅ Create segments and send campaigns

**Your specific use case** ("users who attended event 2 years ago and are highly active") is **100% supported** with the existing implementation!

---

*Documented from: `app/Services/CampaignTagService.php` and `tests/Feature/Services/CampaignTagServiceTest.php`*
