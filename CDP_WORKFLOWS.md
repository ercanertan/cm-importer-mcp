# CDP Campaign Workflows - Implementation Guide

**Date Created:** 2025-11-12
**Document Version:** 1.0
**Status:** Ready for Implementation

This document details the three core workflows for the B2B Multi-Org CDP with Campaign Monitor integration.

---

## Table of Contents

1. [Recurring Campaigns (CDP-Triggered)](#recurring-campaigns-cdp-triggered)
2. [New User Registration Sync](#new-user-registration-sync)
3. [Complex Segmented Campaigns](#complex-segmented-campaigns)

---

## 1. Recurring Campaigns (CDP-Triggered)

### Overview

**Pattern:** Laravel Scheduler → Queue Job → Campaign Monitor API
**Use Case:** Daily newsletters, weekly digests, monthly reports with tier-based frequencies
**Why CDP-Triggered:** CM has no native recurring campaign automation

### Campaign Monitor Limitations

Campaign Monitor does **NOT** support:
- ❌ "Send daily/weekly/monthly" automation
- ❌ Dynamic segment refresh before sends
- ❌ Conditional recurring logic (tier-based frequencies)
- ❌ Date-based dynamic subject lines
- ✅ Only manual scheduled sends (one-time)

### Implementation

#### Laravel Scheduler Configuration

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // ==========================================
    // TIER-BASED RECURRING CAMPAIGNS
    // ==========================================

    // Daily: Pro + Enterprise Newsletter
    $schedule->call(function() {
        SendRecurringCampaign::dispatch([
            'type' => 'daily_newsletter',
            'segment_key' => 'pro_enterprise_daily',
            'template_id' => 'cm_template_daily_news',
        ]);
    })->dailyAt('08:00')->timezone('America/New_York');

    // Weekly: Free Tier Digest
    $schedule->call(function() {
        SendRecurringCampaign::dispatch([
            'type' => 'weekly_digest_free',
            'segment_key' => 'free_tier_weekly',
            'template_id' => 'cm_template_weekly_free',
        ]);
    })->weeklyOn(5, '09:00'); // Fridays at 9 AM

    // Weekly: Pro Tier Enhanced Digest
    $schedule->call(function() {
        SendRecurringCampaign::dispatch([
            'type' => 'weekly_digest_pro',
            'segment_key' => 'pro_tier_weekly',
            'template_id' => 'cm_template_weekly_pro',
        ]);
    })->weeklyOn(5, '09:00');

    // Weekly: Enterprise Full Report
    $schedule->call(function() {
        SendRecurringCampaign::dispatch([
            'type' => 'weekly_digest_enterprise',
            'segment_key' => 'enterprise_tier_weekly',
            'template_id' => 'cm_template_weekly_enterprise',
        ]);
    })->weeklyOn(5, '10:00');

    // Monthly: Enterprise Executive Report
    $schedule->call(function() {
        SendRecurringCampaign::dispatch([
            'type' => 'monthly_report',
            'segment_key' => 'enterprise_tier_monthly',
            'template_id' => 'cm_template_monthly_exec',
        ]);
    })->monthlyOn(1, '10:00'); // 1st of month at 10 AM
}
```

#### SendRecurringCampaign Job

```php
// app/Jobs/SendRecurringCampaign.php

class SendRecurringCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 3;
    public $timeout = 60;

    public function __construct(public array $data)
    {
        $this->onQueue('cm-campaigns');
    }

    public function handle()
    {
        $segment = Segment::where('key', $this->data['segment_key'])->first();
        $template = CampaignTemplate::find($this->data['template_id']);

        // Create campaign in CM
        $cmCampaign = CampaignMonitorApi::createCampaign([
            'Subject' => $template->subject . ' - ' . now()->format('M d, Y'),
            'Name' => $this->data['type'] . ' - ' . now()->format('Y-m-d'),
            'FromName' => config('campaign-monitor.from_name'),
            'FromEmail' => config('campaign-monitor.from_email'),
            'ReplyTo' => config('campaign-monitor.reply_to'),
            'TemplateID' => $template->cm_template_id,
            'TemplateContent' => [
                'Multilines' => [
                    ['Content' => $this->buildDynamicContent()]
                ]
            ],
            'SegmentIDs' => [$segment->cm_segment_id]
        ]);

        // Send immediately
        CampaignMonitorApi::sendCampaign($cmCampaign->CampaignID, [
            'ConfirmationEmail' => config('campaign-monitor.notification_email'),
            'SendDate' => 'immediately'
        ]);

        // Track in CDP
        Campaign::create([
            'type' => $this->data['type'],
            'segment_id' => $segment->id,
            'cm_campaign_id' => $cmCampaign->CampaignID,
            'recipient_count' => $segment->estimateCount(),
            'status' => 'sent',
            'sent_at' => now()
        ]);

        Log::info("Recurring campaign sent", [
            'type' => $this->data['type'],
            'cm_campaign_id' => $cmCampaign->CampaignID
        ]);
    }
}
```

#### Persistent CM Segments (One-Time Setup)

```php
// Setup script: Create persistent segments in CM

// Pro/Enterprise Daily Recipients
CampaignMonitorService::createPersistentSegment([
    'name' => 'Pro/Enterprise - Daily Recipients',
    'key' => 'pro_enterprise_daily',
    'rules' => [
        ['field' => 'tier_name', 'operator' => 'IN', 'value' => ['pro', 'enterprise']],
        ['field' => 'activity_score_7d', 'operator' => '>=', 'value' => 30]
    ]
]);

// Free Tier Weekly
CampaignMonitorService::createPersistentSegment([
    'name' => 'Free Tier - Weekly Digest',
    'key' => 'free_tier_weekly',
    'rules' => [
        ['field' => 'tier_name', 'operator' => 'EQUALS', 'value' => 'free']
    ]
]);

// Enterprise Monthly
CampaignMonitorService::createPersistentSegment([
    'name' => 'Enterprise - Monthly Reports',
    'key' => 'enterprise_tier_monthly',
    'rules' => [
        ['field' => 'tier_name', 'operator' => 'EQUALS', 'value' => 'enterprise']
    ]
]);
```

### API Usage

```
Daily newsletter: 2 calls/day × 30 days = 60 calls/month
Weekly digests (3 tiers): 6 calls/week × 4 weeks = 24 calls/month
Monthly report: 2 calls/month

TOTAL: 86 API calls/month for all recurring campaigns
Daily average: ~3 API calls/day
```

### Benefits

✅ **Tier-based frequencies**: Free=weekly, Pro=daily, Enterprise=daily+monthly
✅ **Always-fresh segments**: Persistent fields auto-sync overnight
✅ **Dynamic subject lines**: Date-based personalization
✅ **Conditional logic**: Only send if conditions met (new content, etc.)
✅ **Full audit trail**: Every send tracked in CDP database
✅ **Zero segmentation API calls**: Segments always current

---

## 2. New User Registration Sync

### Overview

**Pattern:** Controller → Event → Listener → Queued Job → CM API
**Use Case:** New user signs up, immediately synced to Campaign Monitor
**Sync Time:** 2-5 seconds (background, non-blocking)

### Complete Workflow

```
User Registration (jane@org.com)
    ↓
RegisterController (CDP)
├── Validate registration data
├── Create/attach Organization ("Org Inc.")
├── Create User (id: 123, email: jane@org.com)
├── Set tier (default: 'free')
├── Record product opt-ins (subscriptions table)
├── Initialize activity_score_7d = 0, activity_score_30d = 0
├── Set permission_to_track (GDPR consent)
└── User sees: "✓ Registration successful!"
    ↓ (Instant response, no waiting)
Event: UserWasRegistered::dispatch($user)
    ↓
Listener: SyncUserToCampaignMonitor
└── Queue: AddSubscriberToCm::dispatch($user)->onQueue('cm-sync')
    ↓ (Background processing, 2-5 seconds)
Job: AddSubscriberToCm
├── Check if user already exists in CM (duplicate handling)
├── Build custom fields payload:
│   ├── cm_subscriber_id: 123 (CDP user ID)
│   ├── tier_name: "free"
│   ├── tier_id: 1
│   ├── primary_org_id: 1
│   ├── primary_org_name: "Org Inc."
│   ├── active_products_count: 2
│   ├── activity_score_7d: 0 (new user)
│   ├── activity_score_30d: 0
│   ├── last_login_date: 2025-11-12
│   ├── account_status: "active"
│   └── temp_campaign_tag: "" (empty, ready for campaigns)
├── ConsentToTrack: Yes/No (GDPR)
└── Resubscribe: false (respect previous unsubscribe)
    ↓
API Call: POST /api/v3.3/subscribers/{list_id}.json
    ↓ (HTTP 201 Created)
Success
├── Update user.cm_synced_at = now()
├── Update user.cm_status = 'active'
├── Log sync completion
└── User ready to receive campaigns
```

### Implementation

#### Controller

```php
// app/Http/Controllers/Auth/RegisterController.php

protected function create(array $data)
{
    return DB::transaction(function() use ($data) {
        // Create user
        $user = User::create([
            'fullname' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'organization_id' => $organization->id,
            'permission_to_track' => $data['consent_tracking'] ?? true,

            // Initialize activity scores
            'activity_score_7d' => 0,
            'activity_score_30d' => 0,

            // Track registration
            'last_login_at' => now(),

            // CM sync status
            'cm_status' => null,
            'cm_synced_at' => null
        ]);

        // Attach to organization
        $organization->users()->attach($user->id, [
            'is_primary' => true,
            'is_manual' => false
        ]);

        // Handle product opt-ins
        if (!empty($data['product_subscriptions'])) {
            foreach ($data['product_subscriptions'] as $productId) {
                $user->productSubscriptions()->create([
                    'product_id' => $productId,
                    'is_active' => true,
                    'subscribed_at' => now()
                ]);
            }
        }

        // Dispatch event (triggers CM sync)
        event(new UserWasRegistered($user));

        return $user;
    });
}
```

#### Event

```php
// app/Events/UserWasRegistered.php

class UserWasRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user) {}
}
```

#### Listener

```php
// app/Listeners/SyncUserToCampaignMonitor.php

class SyncUserToCampaignMonitor
{
    public function handle(UserWasRegistered $event)
    {
        // Queue sync job (non-blocking)
        AddSubscriberToCm::dispatch($event->user)
            ->onQueue('cm-sync');

        // Optional: Chain welcome email after sync
        AddSubscriberToCm::dispatch($event->user)
            ->chain([
                new SendWelcomeEmail($event->user)
            ]);
    }
}
```

#### Job with Duplicate Handling

```php
// app/Jobs/AddSubscriberToCm.php

class AddSubscriberToCm implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min
    public $timeout = 30;

    public function __construct(public User $user)
    {
        $this->onQueue('cm-sync');
    }

    public function handle()
    {
        $listId = config('campaign-monitor.list_id');

        try {
            // Check if user already exists in CM
            $existing = CampaignMonitorApi::getSubscriber($listId, $this->user->email);

            if ($existing) {
                // Update existing subscriber
                CampaignMonitorApi::updateSubscriber($listId, [
                    'EmailAddress' => $this->user->email,
                    'Name' => $this->user->fullname,
                    'CustomFields' => $this->buildCustomFields(),
                    'Resubscribe' => false, // Respect unsubscribe status
                    'RestartSubscriptionBasedAutoresponders' => false
                ]);

                Log::info('Updated existing CM subscriber', [
                    'user_id' => $this->user->id
                ]);
            } else {
                // Add new subscriber
                CampaignMonitorApi::addSubscriber($listId, [
                    'EmailAddress' => $this->user->email,
                    'Name' => $this->user->fullname,
                    'CustomFields' => $this->buildCustomFields(),
                    'ConsentToTrack' => $this->user->permission_to_track ? 'Yes' : 'No',
                    'Resubscribe' => false
                ]);

                Log::info('Added new CM subscriber', [
                    'user_id' => $this->user->id
                ]);
            }

            // Update sync timestamp
            $this->user->update([
                'cm_synced_at' => now(),
                'cm_status' => 'active'
            ]);

        } catch (\Exception $e) {
            Log::error('CM sync failed for user registration', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage()
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    protected function buildCustomFields(): array
    {
        return [
            ['Key' => 'cm_subscriber_id', 'Value' => (string) $this->user->id],
            ['Key' => 'tier_name', 'Value' => $this->user->primaryOrganization->tier->name ?? 'free'],
            ['Key' => 'tier_id', 'Value' => (string) ($this->user->primaryOrganization->tier->id ?? 1)],
            ['Key' => 'primary_org_id', 'Value' => (string) $this->user->primaryOrganization->id],
            ['Key' => 'primary_org_name', 'Value' => $this->user->primaryOrganization->name],
            ['Key' => 'active_products_count', 'Value' => (string) $this->user->activeProductSubscriptions()->count()],
            ['Key' => 'activity_score_7d', 'Value' => '0'],
            ['Key' => 'activity_score_30d', 'Value' => '0'],
            ['Key' => 'last_login_date', 'Value' => $this->user->last_login_at?->format('Y-m-d') ?? now()->format('Y-m-d')],
            ['Key' => 'account_status', 'Value' => 'active'],
            ['Key' => 'temp_campaign_tag', 'Value' => ''],
        ];
    }

    public function failed(\Throwable $exception)
    {
        Log::error('CM sync permanently failed for user registration', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
```

### Performance

```
10 registrations/day = 300 API calls/month (negligible)
100 registrations/day = 3,000 API calls/month (manageable)
500 registrations/day = 15,000 API calls/month (no problem)

Spike scenario: 1,000 registrations in 1 hour
└── Queue processes at 1 call/second = ~17 minutes to sync all
```

### Queue Configuration for Spikes

```php
// Supervisor configuration
[program:laravel-cm-sync-worker]
command=php artisan queue:work --queue=cm-sync --tries=3
numprocs=3  // 3 concurrent workers handle spikes
```

---

## 3. Complex Segmented Campaigns

### Overview

**Pattern:** "Brain (CDP) and Voice (CM)" - CDP queries, CM delivers
**Use Case:** Complex multi-table queries (e.g., "Paid tier + opted into Product A + attended 2024 webinar")
**Why Dynamic Tags:** CM cannot handle complex JOINs or behavioral criteria

### Complete Workflow

```
Admin Action: Build segment in CDP UI
    ↓
CDP Query Execution
├── Complex Eloquent query with multiple JOINs
├── Users + Organizations + Products + Events + Purchases
├── Result: 2,000 users matching all criteria
└── Query time: 0.5-2 seconds (with proper indexes)
    ↓
Admin Clicks "Send Campaign"
├── Preview: Shows first 10 users, total count
├── Confirm: "Send to 2,000 users"
└── Job queued: "✓ Campaign queued!"
    ↓ (Background processing)
TagAndSendCampaign Job
├── Step 1: Generate unique tag
│   └── "seg_20251112_143022_webinar"
│
├── Step 2: Tag users in CM (bulk import)
│   ├── Batch 2,000 users into chunks of 1,000
│   ├── API Call 1: Import 1,000 users with temp_campaign_tag
│   ├── API Call 2: Import 1,000 users with temp_campaign_tag
│   └── Time: 2-3 seconds
│
├── Step 3: Create segment in CM
│   ├── API Call 3: Create segment
│   ├── Rule: temp_campaign_tag EQUALS "seg_20251112_143022_webinar"
│   └── Returns: segment_id
│
├── Step 4: Create and send campaign
│   ├── API Call 4: Create campaign
│   ├── API Call 5: Send campaign to segment_id
│   └── Time: 1-2 seconds
│
├── Step 5: Track in CDP
│   └── Save campaign record (cm_campaign_id, tag, recipients, etc.)
│
└── Step 6: Schedule cleanup (2 hours later)
    ├── CleanupCampaignTag job queued
    └── Delayed execution: 2 hours
    ↓ (2 hours later)
Cleanup Job
├── Clear temp_campaign_tag for all 2,000 users
├── API Call 6-7: Bulk import with empty tag value
├── Optional: Delete CM segment
└── Mark campaign as cleaned up in CDP

TOTAL: 7 API calls, ~10 seconds execution time
```

### Implementation

#### Admin Controller

```php
// app/Http/Controllers/Admin/CampaignController.php

public function sendComplex(Request $request)
{
    $validated = $request->validate([
        'segment_id' => 'required|exists:segments,id',
        'template_id' => 'required|string',
        'subject' => 'required|string|max:255',
        'campaign_name' => 'required|string|max:255'
    ]);

    $segment = Segment::findOrFail($validated['segment_id']);
    $users = $segment->execute(); // Execute complex query

    if ($users->isEmpty()) {
        return back()->withErrors(['segment' => 'No users match this segment.']);
    }

    // Preview mode
    if ($request->has('preview')) {
        return view('admin.campaigns.preview', [
            'segment' => $segment,
            'users' => $users->take(10),
            'total_count' => $users->count()
        ]);
    }

    // Dispatch campaign job
    TagAndSendCampaign::dispatch(
        $users,
        $validated['campaign_name'],
        $validated['template_id'],
        $validated['subject'],
        $segment->id
    );

    return redirect()->route('admin.campaigns.index')
        ->with('success', sprintf(
            'Campaign "%s" queued! Sending to %d users.',
            $validated['campaign_name'],
            $users->count()
        ));
}
```

#### TagAndSendCampaign Job

```php
// app/Jobs/TagAndSendCampaign.php

class TagAndSendCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300; // 5 minutes for large segments

    public function __construct(
        public Collection $users,
        public string $campaignName,
        public string $templateId,
        public string $subject,
        public ?int $segmentId = null
    ) {
        $this->onQueue('cm-campaigns');
    }

    public function handle()
    {
        // Generate unique tag
        $tag = 'seg_' . now()->format('Ymd_His') . '_' . Str::slug($this->campaignName);

        Log::info('Starting complex campaign', [
            'campaign' => $this->campaignName,
            'tag' => $tag,
            'recipient_count' => $this->users->count()
        ]);

        // Tag users in CM
        $this->tagUsersInCM($tag);

        // Create CM segment
        $cmSegment = $this->createCMSegment($tag);

        // Create and send campaign
        $cmCampaign = $this->createAndSendCampaign($cmSegment);

        // Track in CDP
        $campaign = $this->trackCampaignInCDP($tag, $cmSegment, $cmCampaign);

        // Schedule cleanup
        $this->scheduleCleanup($tag, $cmSegment->SegmentID);

        Log::info('Complex campaign sent successfully', [
            'campaign_id' => $campaign->id,
            'cm_campaign_id' => $cmCampaign->CampaignID
        ]);
    }

    protected function tagUsersInCM(string $tag): void
    {
        $listId = config('campaign-monitor.list_id');

        $this->users->chunk(1000)->each(function($chunk) use ($tag, $listId) {
            $subscribers = $chunk->map(fn($user) => [
                'EmailAddress' => $user->email,
                'CustomFields' => [
                    ['Key' => 'temp_campaign_tag', 'Value' => $tag],
                    ['Key' => 'cm_subscriber_id', 'Value' => (string) $user->id]
                ]
            ])->toArray();

            CampaignMonitorApi::importSubscribers($listId, [
                'Subscribers' => $subscribers,
                'Resubscribe' => true,
                'QueueSubscriptionBasedAutoResponders' => false
            ]);

            sleep(1); // Rate limiting
        });
    }

    protected function createCMSegment(string $tag): object
    {
        return CampaignMonitorApi::createSegment(
            config('campaign-monitor.list_id'),
            [
                'Title' => $this->campaignName . ' - ' . now()->format('Y-m-d H:i'),
                'RuleGroups' => [[
                    'Rules' => [[
                        'Subject' => 'temp_campaign_tag',
                        'Clauses' => ['EQUALS ' . $tag]
                    ]]
                ]]
            ]
        );
    }

    protected function createAndSendCampaign(object $cmSegment): object
    {
        $campaign = CampaignMonitorApi::createCampaign(
            config('campaign-monitor.client_id'),
            [
                'Subject' => $this->subject,
                'Name' => $this->campaignName . ' - ' . now()->format('Y-m-d'),
                'FromName' => config('campaign-monitor.from_name'),
                'FromEmail' => config('campaign-monitor.from_email'),
                'ReplyTo' => config('campaign-monitor.reply_to'),
                'TemplateID' => $this->templateId,
                'SegmentIDs' => [$cmSegment->SegmentID]
            ]
        );

        CampaignMonitorApi::sendCampaign($campaign->CampaignID, [
            'ConfirmationEmail' => config('campaign-monitor.notification_email'),
            'SendDate' => 'immediately'
        ]);

        return $campaign;
    }

    protected function trackCampaignInCDP(string $tag, object $cmSegment, object $cmCampaign): Campaign
    {
        return Campaign::create([
            'name' => $this->campaignName,
            'type' => 'complex_segment',
            'segment_id' => $this->segmentId,
            'cm_campaign_id' => $cmCampaign->CampaignID,
            'cm_segment_id' => $cmSegment->SegmentID,
            'campaign_tag' => $tag,
            'recipient_count' => $this->users->count(),
            'status' => 'sent',
            'sent_at' => now(),
            'metadata' => [
                'template_id' => $this->templateId,
                'subject' => $this->subject
            ]
        ]);
    }

    protected function scheduleCleanup(string $tag, string $segmentId): void
    {
        CleanupCampaignTag::dispatch(
            $tag,
            $segmentId,
            $this->users->pluck('id')->toArray()
        )->delay(now()->addHours(2));
    }
}
```

#### Cleanup Job

```php
// app/Jobs/CleanupCampaignTag.php

class CleanupCampaignTag implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 3;
    public $timeout = 180;

    public function __construct(
        public string $tag,
        public string $cmSegmentId,
        public array $userIds
    ) {
        $this->onQueue('cm-cleanup');
    }

    public function handle()
    {
        Log::info('Starting tag cleanup', [
            'tag' => $this->tag,
            'user_count' => count($this->userIds)
        ]);

        $listId = config('campaign-monitor.list_id');

        // Clear temp_campaign_tag for all users
        User::whereIn('id', $this->userIds)
            ->chunk(1000, function($chunk) use ($listId) {
                $subscribers = $chunk->map(fn($user) => [
                    'EmailAddress' => $user->email,
                    'CustomFields' => [
                        ['Key' => 'temp_campaign_tag', 'Value' => '']
                    ]
                ])->toArray();

                CampaignMonitorApi::importSubscribers($listId, [
                    'Subscribers' => $subscribers,
                    'Resubscribe' => true
                ]);

                sleep(1);
            });

        // Delete CM segment (keeps CM clean)
        try {
            CampaignMonitorApi::deleteSegment($this->cmSegmentId);
            Log::info('Deleted CM segment', ['segment_id' => $this->cmSegmentId]);
        } catch (\Exception $e) {
            Log::warning('Could not delete CM segment', [
                'segment_id' => $this->cmSegmentId,
                'error' => $e->getMessage()
            ]);
        }

        // Mark campaign as cleaned up
        Campaign::where('campaign_tag', $this->tag)
            ->update(['cleanup_completed_at' => now()]);

        Log::info('Tag cleanup completed', ['tag' => $this->tag]);
    }
}
```

### Complex Query Examples

```php
// Example 1: Paid tier + Product A + 2024 Webinar
$users = User::whereHas('organization.tier', fn($q) =>
        $q->whereIn('name', ['pro', 'enterprise'])
    )
    ->whereHas('productSubscriptions', fn($q) =>
        $q->where('product_id', 5)->where('is_active', true)
    )
    ->whereHas('events', fn($q) =>
        $q->where('name', '2024 Webinar')
          ->where('attended_at', '>', '2024-01-01')
    )
    ->get();

// Example 2: High activity + No purchase in 60 days
$users = User::where('activity_score_30d', '>=', 80)
    ->where('last_login_at', '>', now()->subDays(7))
    ->whereDoesntHave('purchases', fn($q) =>
        $q->where('created_at', '>', now()->subDays(60))
    )
    ->get();

// Example 3: Event attendance + Product interest + No campaign sent
$users = User::whereHas('events', fn($q) =>
        $q->where('name', 'SaaS Summit 2023')
    )
    ->whereHas('productSubscriptions', fn($q) =>
        $q->where('product_id', 10)
    )
    ->whereDoesntHave('campaigns', fn($q) =>
        $q->where('name', 'like', '%Black Friday%')
    )
    ->get();
```

### Performance at Scale

```
Small segment (500 users):
└── 1 (tag) + 1 (segment) + 2 (send) + 1 (cleanup) = 5 API calls
    Time: ~5 seconds

Medium segment (5,000 users):
└── 5 (tag) + 1 (segment) + 2 (send) + 5 (cleanup) = 13 API calls
    Time: ~15 seconds

Large segment (20,000 users):
└── 20 (tag) + 1 (segment) + 2 (send) + 20 (cleanup) = 43 API calls
    Time: ~60 seconds

Your example (2,000 users):
└── 2 + 1 + 2 + 2 = 7 API calls
    Time: ~10 seconds
```

---

## API Usage Summary

### Monthly Total (60K Users)

```
Recurring Campaigns:
├── Daily newsletter: 60 calls/month
├── Weekly digests (3 tiers): 24 calls/month
├── Monthly reports: 2 calls/month
└── Subtotal: 86 calls/month

Background Sync:
├── Daily activity scores: 30 calls/month
├── Weekly activity recalc: 12 calls/month
├── Tier changes: 15 calls/month
└── Subtotal: 57 calls/month

New User Registrations:
└── ~10/day × 30 = 300 calls/month

Complex Campaigns (ad-hoc):
└── ~5 campaigns × 7 calls avg = 35 calls/month

TOTAL: ~478 API calls/month
Daily average: ~16 API calls/day
```

**Well within Campaign Monitor's acceptable usage range.**

---

## Queue Configuration

### Supervisor Setup

```ini
[program:laravel-cm-sync]
command=php artisan queue:work --queue=cm-sync --tries=3
process_name=%(program_name)s_%(process_num)02d
numprocs=3
autostart=true
autorestart=true
user=www-data

[program:laravel-cm-campaigns]
command=php artisan queue:work --queue=cm-campaigns --tries=3
process_name=%(program_name)s_%(process_num)02d
numprocs=2
autostart=true
autorestart=true
user=www-data

[program:laravel-cm-cleanup]
command=php artisan queue:work --queue=cm-cleanup --tries=3
process_name=%(program_name)s_%(process_num)02d
numprocs=1
autostart=true
autorestart=true
user=www-data
```

### Queue Priorities

```php
// config/queue.php
'connections' => [
    'database' => [
        'driver' => 'database',
        'queue' => 'default',
        'retry_after' => 90,
    ],
],

// Queue processing order (highest to lowest priority):
// 1. cm-sync (user registration, immediate sync)
// 2. cm-campaigns (recurring & complex campaigns)
// 3. cm-cleanup (tag cleanup, low priority)
// 4. webhooks (CM webhook processing)
// 5. default (everything else)
```

---

## Best Practices

### 1. Always Preview Before Sending

```php
// Show preview with first 10-50 users
if ($request->has('preview')) {
    return view('admin.campaigns.preview', [
        'users' => $users->take(10),
        'total_count' => $users->count()
    ]);
}
```

### 2. Add Campaign Completion Notifications

```php
// Notify admin when complex campaign completes
protected function notifyCompletion(Campaign $campaign)
{
    Notification::route('mail', config('app.admin_email'))
        ->notify(new CampaignSentNotification($campaign));
}
```

### 3. Implement Retry Logic

```php
public $tries = 3;
public $backoff = [60, 300, 900]; // 1min, 5min, 15min
```

### 4. Log Everything

```php
Log::info('Campaign event', [
    'campaign_id' => $campaign->id,
    'type' => $campaign->type,
    'recipients' => $campaign->recipient_count,
    'status' => $campaign->status
]);
```

### 5. Monitor Queue Health

```bash
# Check queue status
php artisan queue:monitor cm-sync,cm-campaigns,cm-cleanup --max=100

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

---

## Troubleshooting

### Issue: Campaign not sending

**Check:**
1. Queue workers running? `supervisor status`
2. Failed jobs? `php artisan queue:failed`
3. CM API credentials correct? `config:cache`
4. Segment has users? Check preview first

### Issue: Slow tag cleanup

**Solution:** Increase workers for cm-cleanup queue or delay cleanup longer (4-6 hours)

### Issue: Duplicate users in campaigns

**Solution:** Add deduplication in TagAndSendCampaign:
```php
$users = $users->unique('email');
```

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Status:** Production-Ready
