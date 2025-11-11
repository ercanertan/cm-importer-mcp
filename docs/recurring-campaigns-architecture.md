# Recurring Campaigns Architecture

## The Problem

**temp_campaign_tag** works great for one-off campaigns (e.g., "Re-engagement campaign for Product X lapsed users"). But it **FAILS** for recurring campaigns:

### Recurring Campaign Types Needed:
1. **Daily**: "Daily digest for paid_pro tier"
2. **Weekly**: "Weekly newsletter for all active users"
3. **Monthly**: "Monthly report for enterprise tier"

**Why temp_campaign_tag fails:**
- Can only hold ONE value at a time
- Gets cleared after each campaign
- Can't represent permanent segments (like "paid_pro tier")

## The Solution: Permanent Segments via CM Tags

Instead of using `temp_campaign_tag`, use **Campaign Monitor's native tags** for permanent, recurring segments.

---

## Architecture: Two-Track System

### Track 1: Permanent Segments (Recurring Campaigns)
**Use CM Tags** - Synced continuously via observers

### Track 2: One-Off Campaigns (Complex Queries)
**Use temp_campaign_tag** - Set temporarily, cleared after send

---

## Track 1: Permanent Segments (The Main Use Case)

### Strategy: Sync Important Segments as CM Tags

Instead of custom fields, use **tags** to represent segments that need recurring campaigns:

#### Example Tags (Automatically Synced):
```
tier:free
tier:paid_pro
tier:paid_premium
tier:enterprise

status:active
status:inactive
status:at_risk

engagement:high
engagement:medium
engagement:low

org_tier:free
org_tier:paid_pro
org_tier:enterprise
```

### Implementation

#### 1. User Observer Auto-Tags on Changes

```php
// app/Observers/UserObserver.php

public function updated(User $user): void
{
    // Skip if bulk operation
    if ($this->isBulkOperation()) {
        return;
    }

    // Sync tier tags when tier changes
    if ($user->wasChanged('tier')) {
        $this->syncTierTags($user);
    }

    // Sync engagement tags when engagement score changes
    if ($user->wasChanged('engagement_score')) {
        $this->syncEngagementTags($user);
    }

    // Sync status tags
    if ($user->wasChanged('last_activity_at')) {
        $this->syncStatusTags($user);
    }
}

protected function syncTierTags(User $user): void
{
    // Remove old tier tags
    $allTierTags = ['tier:free', 'tier:paid_pro', 'tier:paid_premium', 'tier:enterprise'];

    foreach ($allTierTags as $tag) {
        app(CampaignMonitorTagService::class)->removeTag($user, $tag);
    }

    // Add new tier tag
    $newTierTag = 'tier:' . $user->tier;
    app(CampaignMonitorTagService::class)->addTag($user, $newTierTag);
}

protected function syncEngagementTags(User $user): void
{
    // Remove old engagement tags
    $allEngagementTags = ['engagement:high', 'engagement:medium', 'engagement:low'];

    foreach ($allEngagementTags as $tag) {
        app(CampaignMonitorTagService::class)->removeTag($user, $tag);
    }

    // Add new engagement tag based on score
    if ($user->engagement_score >= 70) {
        $tag = 'engagement:high';
    } elseif ($user->engagement_score >= 40) {
        $tag = 'engagement:medium';
    } else {
        $tag = 'engagement:low';
    }

    app(CampaignMonitorTagService::class)->addTag($user, $tag);
}
```

#### 2. Create Permanent Segments in CM (One-Time Setup)

```php
// app/Console/Commands/SetupRecurringCampaignSegments.php

public function handle(CmSyncService $cmSyncService)
{
    // Segment: Paid Pro Tier (for daily digest)
    $cmSyncService->createSegment([
        'Title' => 'Paid Pro - Daily Digest Recipients',
        'RuleGroups' => [
            [
                'Rules' => [
                    [
                        'Subject' => '[tier:paid_pro]', // Tag
                        'Clauses' => ['ISSUBSCRIBEDTO']
                    ],
                    [
                        'Subject' => '[status:active]', // Tag
                        'Clauses' => ['ISSUBSCRIBEDTO']
                    ]
                ]
            ]
        ]
    ]);

    // Segment: Enterprise Tier (for monthly reports)
    $cmSyncService->createSegment([
        'Title' => 'Enterprise - Monthly Report Recipients',
        'RuleGroups' => [
            [
                'Rules' => [
                    [
                        'Subject' => '[tier:enterprise]', // Tag
                        'Clauses' => ['ISSUBSCRIBEDTO']
                    ]
                ]
            ]
        ]
    ]);

    // Segment: All Active Users (for weekly newsletter)
    $cmSyncService->createSegment([
        'Title' => 'All Active Users - Weekly Newsletter',
        'RuleGroups' => [
            [
                'Rules' => [
                    [
                        'Subject' => '[status:active]', // Tag
                        'Clauses' => ['ISSUBSCRIBEDTO']
                    ]
                ]
            ]
        ]
    ]);

    $this->info('Recurring campaign segments created!');
}
```

#### 3. Schedule Recurring Campaigns in Laravel (Not CM!)

```php
// app/Console/Commands/SendDailyDigest.php

public function handle(CmSyncService $cmSyncService)
{
    // Get the segment ID for "Paid Pro - Daily Digest Recipients"
    $segmentId = config('campaign-monitor.segments.paid_pro_daily_digest');

    // Create campaign via CM API
    $campaignId = $cmSyncService->createCampaign([
        'Subject' => 'Your Daily Digest - ' . now()->format('F j, Y'),
        'Name' => 'Daily Digest - ' . now()->format('Y-m-d'),
        'FromName' => 'Your App',
        'FromEmail' => 'digest@yourapp.com',
        'ReplyTo' => 'support@yourapp.com',
        'HtmlUrl' => route('email.templates.daily-digest'), // Dynamic template
        'SegmentIDs' => [$segmentId],
    ]);

    // Send immediately
    $cmSyncService->sendCampaign($campaignId);

    $this->info("Daily digest sent to paid_pro tier users!");
}

// Schedule it
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Daily at 9am
    $schedule->command('campaigns:send-daily-digest')
        ->dailyAt('09:00');

    // Weekly newsletter - Monday at 10am
    $schedule->command('campaigns:send-weekly-newsletter')
        ->weeklyOn(1, '10:00');

    // Monthly report - 1st of month at 8am
    $schedule->command('campaigns:send-monthly-report')
        ->monthlyOn(1, '08:00');
}
```

---

## Track 2: One-Off Complex Campaigns (temp_campaign_tag)

For **complex, one-time queries** that can't be represented by permanent segments:

### Example: "Users who haven't logged in for 30 days AND have Product X subscription AND are in paid tier"

```php
// This is too complex for a permanent segment
$users = User::query()
    ->whereHas('products', fn($q) => $q->where('slug', 'product-x'))
    ->whereIn('tier', ['paid_pro', 'paid_premium'])
    ->where('last_login_at', '<', now()->subDays(30))
    ->get();

// Use temp_campaign_tag workflow
$campaignTag = 'campaign_product_x_reengagement_' . now()->format('Ymd');

// Tag users in Laravel
CampaignTagService::tagUsersBulk($users->pluck('id')->toArray(), $campaignTag);

// Sync tags to CM
TagUsersBulkJob::dispatch($users->pluck('id')->toArray(), $campaignTag);

// Create temporary segment in CM
$segmentId = $cmSyncService->createCampaignTagSegment(
    $campaignTag,
    'Product X Re-engagement - ' . now()->format('Y-m-d')
);

// Send campaign
$campaignId = $cmSyncService->createCampaign([...]);
$cmSyncService->sendCampaign($campaignId);

// Cleanup after 1 hour
ClearTagBulkJob::dispatch($campaignTag)->delay(now()->addHour());

// Delete temporary segment
$cmSyncService->deleteSegment($segmentId);
```

---

## Comparison: When to Use What

| Use Case | Method | Why |
|----------|--------|-----|
| Daily digest for paid_pro tier | **Permanent Segment** (Tag: `tier:paid_pro`) | Recurring, simple rule |
| Weekly newsletter for all active | **Permanent Segment** (Tag: `status:active`) | Recurring, simple rule |
| Monthly report for enterprise | **Permanent Segment** (Tag: `tier:enterprise`) | Recurring, simple rule |
| Re-engagement for lapsed Product X users | **temp_campaign_tag** | One-off, complex query |
| A/B test campaign | **temp_campaign_tag** | One-off, random sample |
| Birthday emails | **Permanent Segment** (Tag: `birth_month:january`) | Recurring, simple rule |

---

## Managing Everything from CDP (No CM Login Required)

### Admin UI in Laravel

```php
// routes/web.php
Route::prefix('admin/campaigns')->group(function() {
    // View all recurring campaigns
    Route::get('/', [CampaignController::class, 'index']);

    // Create new recurring campaign
    Route::get('/create', [CampaignController::class, 'create']);
    Route::post('/', [CampaignController::class, 'store']);

    // Send one-off campaign
    Route::get('/send-oneoff', [CampaignController::class, 'sendOneOff']);

    // View campaign analytics
    Route::get('/{campaign}/analytics', [CampaignController::class, 'analytics']);
});
```

### Database Schema for Campaign Management

```php
Schema::create('campaign_templates', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // "Daily Digest for Paid Pro"
    $table->enum('frequency', ['daily', 'weekly', 'monthly', 'one_off']);
    $table->string('cm_segment_id')->nullable(); // For recurring
    $table->json('segment_criteria')->nullable(); // For Laravel query
    $table->string('subject_template'); // "Your Daily Digest - {date}"
    $table->string('from_name');
    $table->string('from_email');
    $table->string('reply_to');
    $table->string('template_url'); // URL to HTML template
    $table->boolean('active')->default(true);
    $table->time('send_time')->nullable(); // For scheduled
    $table->integer('day_of_week')->nullable(); // For weekly (1-7)
    $table->integer('day_of_month')->nullable(); // For monthly (1-31)
    $table->timestamps();
});

Schema::create('campaign_sends', function (Blueprint $table) {
    $table->id();
    $table->foreignId('campaign_template_id')->nullable()->constrained();
    $table->string('cm_campaign_id')->unique();
    $table->string('name');
    $table->string('subject');
    $table->integer('recipient_count')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->integer('opens')->default(0);
    $table->integer('clicks')->default(0);
    $table->integer('bounces')->default(0);
    $table->integer('unsubscribes')->default(0);
    $table->json('metadata')->nullable();
    $table->timestamps();
});
```

### Admin Controller

```php
class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = CampaignTemplate::with('sends')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        // Get available segments from CM
        $segments = app(CmSyncService::class)->listSegments();

        return view('admin.campaigns.create', compact('segments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'frequency' => 'required|in:daily,weekly,monthly,one_off',
            'cm_segment_id' => 'nullable|string',
            'subject_template' => 'required|string',
            // ... more validation
        ]);

        $campaign = CampaignTemplate::create($validated);

        // If recurring, add to schedule
        if ($campaign->frequency !== 'one_off') {
            // Update Laravel scheduler dynamically or use queue
        }

        return redirect()->route('admin.campaigns.index')
            ->with('success', 'Campaign created!');
    }

    public function analytics(CampaignTemplate $campaign)
    {
        $sends = $campaign->sends()
            ->orderBy('sent_at', 'desc')
            ->get();

        $stats = [
            'total_sent' => $sends->sum('recipient_count'),
            'total_opens' => $sends->sum('opens'),
            'total_clicks' => $sends->sum('clicks'),
            'avg_open_rate' => $sends->avg('open_rate'),
            'avg_click_rate' => $sends->avg('click_rate'),
        ];

        return view('admin.campaigns.analytics', compact('campaign', 'sends', 'stats'));
    }
}
```

---

## New Service: CampaignMonitorTagService

Separate service for managing CM tags (different from temp_campaign_tag):

```php
// app/Services/CampaignMonitorTagService.php

class CampaignMonitorTagService
{
    /**
     * Add a tag to a user in Campaign Monitor
     */
    public function addTag(User $user, string $tag): bool
    {
        if (!$user->cm_subscriber_id) {
            return false;
        }

        try {
            $subscribers = new \CS_REST_Subscribers(
                config('campaign-monitor.list_id'),
                ['api_key' => config('campaign-monitor.api_key')]
            );

            $result = $subscribers->add_tags($user->email, [$tag]);

            if ($result->was_successful()) {
                Log::info('Tag added to user in CM', [
                    'user_id' => $user->id,
                    'tag' => $tag,
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to add tag to user in CM', [
                'user_id' => $user->id,
                'tag' => $tag,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Remove a tag from a user in Campaign Monitor
     */
    public function removeTag(User $user, string $tag): bool
    {
        if (!$user->cm_subscriber_id) {
            return false;
        }

        try {
            $subscribers = new \CS_REST_Subscribers(
                config('campaign-monitor.list_id'),
                ['api_key' => config('campaign-monitor.api_key')]
            );

            $result = $subscribers->remove_tags($user->email, [$tag]);

            if ($result->was_successful()) {
                Log::info('Tag removed from user in CM', [
                    'user_id' => $user->id,
                    'tag' => $tag,
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to remove tag from user in CM', [
                'user_id' => $user->id,
                'tag' => $tag,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync all tier/engagement/status tags for a user
     */
    public function syncAllTags(User $user): void
    {
        $this->syncTierTag($user);
        $this->syncEngagementTag($user);
        $this->syncStatusTag($user);
        $this->syncOrgTierTag($user);
    }

    protected function syncTierTag(User $user): void
    {
        // Remove all tier tags
        $this->removeTag($user, 'tier:free');
        $this->removeTag($user, 'tier:paid_pro');
        $this->removeTag($user, 'tier:paid_premium');
        $this->removeTag($user, 'tier:enterprise');

        // Add current tier tag
        if ($user->tier) {
            $this->addTag($user, 'tier:' . $user->tier);
        }
    }

    // Similar methods for engagement, status, org_tier...
}
```

---

## One-Way Sync: Laravel → CM Only

**Key Principle**: New subscriptions **always** originate in Laravel

### When User is Created in Laravel:

```php
// app/Observers/UserObserver.php

public function created(User $user): void
{
    // Skip if bulk import
    if ($this->isBulkOperation()) {
        return;
    }

    // Sync to Campaign Monitor
    app(CmSyncService::class)->syncUser($user);

    // Add initial tags
    app(CampaignMonitorTagService::class)->syncAllTags($user);
}
```

### No Reverse Sync

- **Never** import subscribers from CM into Laravel
- **Never** let CM be the source of truth for user data
- CM is purely an **email delivery engine**

---

## Summary

### For Recurring Campaigns (Daily/Weekly/Monthly):
✅ Use **permanent CM tags** (`tier:paid_pro`, `status:active`, etc.)
✅ Create **permanent segments** in CM (one-time setup)
✅ Schedule via **Laravel scheduler** (not CM)
✅ Manage entirely from **CDP admin UI**

### For One-Off Complex Campaigns:
✅ Use **temp_campaign_tag** workflow
✅ Tag → Sync → Segment → Send → Cleanup

### One-Way Sync:
✅ Laravel → CM only
✅ New subscribers always created in Laravel
✅ Tags auto-synced via observers
✅ No CM login required for day-to-day operations

---

*Next Steps: Implement CampaignMonitorTagService and update observers to sync tags automatically.*
