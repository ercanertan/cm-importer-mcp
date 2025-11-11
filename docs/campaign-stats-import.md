# Campaign Monitor Statistics Import

## Overview

Import historical campaign statistics from Campaign Monitor into your CDP (Customer Data Platform). This enables you to:

- Track campaign performance over time
- Analyze open rates, click rates, and engagement metrics
- Link email engagement data to specific campaigns
- Generate reports and insights from historical campaign data

---

## How It Works

### Architecture

```
Campaign Monitor API
        ↓
CmCampaignStatsService
        ↓
cm_campaigns table (MySQL)
        ↓
Analytics & Reporting
```

### Data Flow

1. **Fetch Campaigns**: Service calls CM API to get list of all sent campaigns
2. **Fetch Stats**: For each campaign, fetch detailed statistics (opens, clicks, bounces, etc.)
3. **Calculate Rates**: Calculate open rate, click rate, bounce rate, unsubscribe rate
4. **Store in DB**: Save campaign data and stats in `cm_campaigns` table
5. **Track Sync**: Record when stats were last synced for future updates

---

## Usage

### Import All Campaigns

Import all sent campaigns from Campaign Monitor:

```bash
php artisan cm:import-campaign-stats
```

**Example Output:**
```
Importing campaigns from Campaign Monitor...
✓ Campaign import completed!

┌──────────┬───────┐
│ Metric   │ Count │
├──────────┼───────┤
│ Imported │ 47    │
│ Skipped  │ 2     │
│ Failed   │ 0     │
└──────────┴───────┘
```

### Import with Limit

Import only the most recent N campaigns:

```bash
php artisan cm:import-campaign-stats --limit=10
```

**Use Case**: Testing or importing recent campaigns only

### Sync Existing Campaigns

Re-sync statistics for campaigns already in database (updates stats without re-importing):

```bash
php artisan cm:import-campaign-stats --sync-only
```

**Use Case**: Update stats for existing campaigns (daily/weekly refresh)

### Show Performance Summary

Display campaign performance summary after import:

```bash
php artisan cm:import-campaign-stats --show-summary
```

**Example Output:**
```
=== Campaign Performance Summary ===

┌─────────────────────────────────┬─────────────┐
│ Metric                          │ Value       │
├─────────────────────────────────┼─────────────┤
│ Total Campaigns                 │ 47          │
│ Total Recipients                │ 2,450,800   │
│ Total Opens                     │ 532,176     │
│ Total Clicks                    │ 98,432      │
│ Average Open Rate               │ 21.72%      │
│ Average Click Rate              │ 4.02%       │
│ High Engagement Campaigns       │ 12          │
│ Medium Engagement Campaigns     │ 28          │
│ Low Engagement Campaigns        │ 7           │
└─────────────────────────────────┴─────────────┘
```

---

## Database Schema

### `cm_campaigns` Table

**Campaign Monitor IDs:**
- `cm_campaign_id` (unique) - Campaign Monitor's campaign ID
- `cm_list_id` - List ID the campaign was sent to

**Campaign Details:**
- `name` - Campaign name
- `subject` - Email subject line
- `from_name` - Sender name
- `from_email` - Sender email
- `reply_to` - Reply-to email
- `sent_at` - When campaign was sent
- `status` - Campaign status (sent, draft, scheduled, bounced)
- `web_version_url` - URL to web version
- `web_version_text_url` - URL to text version

**Statistics:**
- `total_recipients` - Total number of recipients
- `total_opens` - Total opens (includes multiple opens per user)
- `unique_opens` - Unique recipients who opened
- `total_clicks` - Total clicks (includes multiple clicks per user)
- `unique_clicks` - Unique recipients who clicked
- `total_bounces` - Total bounced emails
- `total_unsubscribes` - Total unsubscribes
- `total_spam_complaints` - Total spam complaints
- `forwards` - Number of times forwarded
- `likes` - Social likes
- `mentions` - Social mentions

**Calculated Metrics:**
- `open_rate` - Percentage of recipients who opened (unique_opens / total_recipients * 100)
- `click_rate` - Percentage of recipients who clicked (unique_clicks / total_recipients * 100)
- `bounce_rate` - Percentage of emails that bounced
- `unsubscribe_rate` - Percentage who unsubscribed

**Sync Tracking:**
- `stats_last_synced_at` - When stats were last synced from CM
- `is_active` - Whether to actively track this campaign (default: true)

---

## Use Cases

### 1. Historical Data Backfill

**Scenario**: You've been using Campaign Monitor for years, now you want all historical data in your CDP.

```bash
# Import all campaigns
php artisan cm:import-campaign-stats

# Result: All sent campaigns now in database with full statistics
```

### 2. Daily Stats Refresh

**Scenario**: Keep campaign stats up-to-date without re-importing everything.

```bash
# Add to cron (runs daily at 2am)
0 2 * * * cd /path/to/app && php artisan cm:import-campaign-stats --sync-only
```

**Result**: Statistics refreshed daily for all existing campaigns

### 3. New Campaign Import

**Scenario**: Import only recent campaigns sent in the last month.

```bash
# Import recent campaigns
php artisan cm:import-campaign-stats --limit=30

# Check summary
php artisan cm:import-campaign-stats --show-summary
```

### 4. Link Engagement to Campaigns

**Scenario**: When a webhook comes in with an email open/click, you want to know which campaign it came from.

**Future Enhancement**: Store `cm_campaign_id` in `email_engagements` table when webhooks are received. This allows you to:
- See which campaigns drive the most engagement
- Calculate per-campaign engagement scores
- Identify your best-performing campaigns

---

## API Integration

### CmCampaignStatsService Methods

```php
use App\Services\CmCampaignStatsService;

$service = new CmCampaignStatsService();

// Check if CM is configured
if (!$service->isConfigured()) {
    // Handle missing API credentials
}

// Fetch all campaigns from CM (returns array of campaign objects)
$campaigns = $service->fetchAllCampaigns($limit = 50);

// Fetch stats for specific campaign
$stats = $service->fetchCampaignStats('abc123campaignid');

// Import single campaign
$campaign = $service->importCampaign($campaignData);

// Import all campaigns
$result = $service->importAllCampaigns($limit = null);
// Returns: ['imported' => 47, 'failed' => 0, 'skipped' => 2]

// Sync existing campaign stats
$result = $service->syncCampaignStats($limit = null);
// Returns: ['synced' => 45, 'failed' => 2]

// Get performance summary
$summary = $service->getCampaignSummary();
// Returns: ['total_campaigns' => 47, 'avg_open_rate' => 21.72, ...]
```

---

## Model Usage

### CmCampaign Model

```php
use App\Models\CmCampaign;

// Get all active campaigns
$campaigns = CmCampaign::active()->get();

// Get campaigns sent after specific date
$recentCampaigns = CmCampaign::sentAfter('2024-01-01')->get();

// Get campaigns by status
$sentCampaigns = CmCampaign::byStatus('sent')->get();

// Get engagement level (computed attribute)
$campaign = CmCampaign::first();
echo $campaign->engagement_level; // 'high', 'medium', or 'low'

// Calculate rates manually
$campaign->calculateRates();
$campaign->save();
```

### Engagement Level Calculation

Engagement level is based on average of open rate + click rate:

- **High**: Average ≥ 30%
- **Medium**: Average 15-29%
- **Low**: Average < 15%

---

## Configuration

### Required Environment Variables

```env
# Campaign Monitor API Credentials
CM_API_KEY=your-api-key-here
CM_CLIENT_ID=your-client-id-here
```

### Optional Configuration

In `config/campaign-monitor.php`:

```php
// Days to keep one-off segments before cleanup
'segment_cleanup_days' => env('CM_SEGMENT_CLEANUP_DAYS', 30),

// Campaign Monitor API Key
'api_key' => env('CM_API_KEY'),

// Campaign Monitor Client ID
'client_id' => env('CM_CLIENT_ID'),
```

---

## Scheduled Tasks

### Automated Daily Sync

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Sync campaign stats daily at 2am
    $schedule->command('cm:import-campaign-stats --sync-only')
        ->dailyAt('02:00')
        ->withoutOverlapping()
        ->runInBackground();

    // Import new campaigns weekly on Monday at 3am
    $schedule->command('cm:import-campaign-stats --limit=50')
        ->weeklyOn(1, '03:00')
        ->withoutOverlapping()
        ->runInBackground();
}
```

---

## Querying Campaign Data

### Example Queries

**Get top 10 campaigns by open rate:**
```php
$topCampaigns = CmCampaign::active()
    ->orderBy('open_rate', 'desc')
    ->limit(10)
    ->get();
```

**Get campaigns with low engagement:**
```php
$lowEngagement = CmCampaign::active()
    ->where('open_rate', '<', 15)
    ->where('click_rate', '<', 3)
    ->get();
```

**Get monthly campaign performance:**
```php
$monthlyCampaigns = CmCampaign::active()
    ->whereBetween('sent_at', ['2024-01-01', '2024-01-31'])
    ->get();

$avgOpenRate = $monthlyCampaigns->avg('open_rate');
$totalRecipients = $monthlyCampaigns->sum('total_recipients');
```

**Get campaigns needing stats refresh (not synced in 24 hours):**
```php
$stale = CmCampaign::active()
    ->where('stats_last_synced_at', '<', now()->subDay())
    ->get();
```

---

## Performance Considerations

### Rate Limiting

Campaign Monitor API has rate limits. The service includes:

- **Batch Processing**: Import campaigns sequentially to avoid rate limits
- **Error Handling**: Failed imports are logged and reported
- **Retry Logic**: Use Laravel queue retry mechanism for failed imports

### Large Imports

For accounts with 100+ campaigns:

```bash
# Import in batches
php artisan cm:import-campaign-stats --limit=50
php artisan cm:import-campaign-stats --limit=50  # run multiple times

# Or use queue (future enhancement)
php artisan cm:import-campaign-stats --queue
```

### Database Indexes

The migration includes indexes on:
- `sent_at` - For date range queries
- `status` - For filtering by status
- `cm_list_id` - For filtering by list
- `is_active` - For filtering active campaigns

---

## Error Handling

### Common Issues

**API Not Configured:**
```
Campaign Monitor API is not configured.
Please set CM_API_KEY and CM_CLIENT_ID in your .env file.
```

**Solution**: Add credentials to `.env`

**API Rate Limit Hit:**
```
Failed to fetch campaigns: Rate limit exceeded
```

**Solution**: Wait and retry, or use `--limit` to import in smaller batches

**Campaign Not Found:**
```
Could not fetch stats for campaign, skipping
```

**Solution**: Campaign may have been deleted in CM or is a draft (not sent)

### Logging

All errors are logged to Laravel's log system:

```php
// View recent errors
tail -f storage/logs/laravel.log | grep "campaign"

// Check specific error
Log::error('Failed to import campaign', [
    'campaign_id' => $campaignData->CampaignID,
    'error' => $e->getMessage(),
]);
```

---

## Future Enhancements

### Planned Features

1. **Link Engagements to Campaigns**
   - Store `cm_campaign_id` in `email_engagements` table
   - Track which campaigns drive user engagement
   - Calculate per-campaign ROI

2. **Campaign Analytics Dashboard**
   - UI to view all campaigns and stats
   - Filter by date range, engagement level, status
   - Charts showing trends over time

3. **Automated Insights**
   - Identify best-performing subject lines
   - Detect engagement patterns
   - Recommend optimal send times

4. **Campaign Segmentation**
   - Link campaigns to CDP user segments
   - Track segment performance across campaigns
   - A/B test different segments

5. **Queue Support**
   - Queue large imports to avoid timeouts
   - Progress tracking for background jobs
   - Email notifications when import completes

---

## Testing

### Manual Testing

```bash
# 1. Test configuration
php artisan tinker
>>> app(App\Services\CmCampaignStatsService::class)->isConfigured()
=> true

# 2. Test fetching campaigns
>>> $service = app(App\Services\CmCampaignStatsService::class);
>>> $campaigns = $service->fetchAllCampaigns(1);
>>> count($campaigns)
=> 1

# 3. Test import
php artisan cm:import-campaign-stats --limit=1

# 4. Verify database
>>> \App\Models\CmCampaign::count()
=> 1

# 5. Check stats
>>> $campaign = \App\Models\CmCampaign::first();
>>> $campaign->open_rate
=> 23.45

# 6. Test sync
php artisan cm:import-campaign-stats --sync-only --limit=1
```

---

## API Response Format

### Campaign List Response

```json
[
  {
    "CampaignID": "abc123",
    "Name": "November Newsletter",
    "Subject": "Check out what's new this month",
    "FromName": "John Doe",
    "FromEmail": "john@example.com",
    "ReplyTo": "support@example.com",
    "WebVersionURL": "https://...",
    "WebVersionTextURL": "https://...",
    "SentDate": "2024-11-01 10:00:00",
    "ListID": "list123"
  }
]
```

### Campaign Summary Response

```json
{
  "Recipients": 50000,
  "TotalOpened": 10500,
  "UniqueOpened": 9800,
  "Clicks": 2100,
  "Unsubscribed": 45,
  "Bounced": 120,
  "SpamComplaints": 3,
  "Forwards": 89,
  "Likes": 234,
  "Mentions": 12
}
```

---

## Summary

✅ **Complete Campaign Stats Import** - Production ready
✅ **Database Schema** - Comprehensive campaign tracking
✅ **Service Layer** - Reusable API integration
✅ **Artisan Commands** - Easy CLI access
✅ **Error Handling** - Robust logging and retry
✅ **Scheduled Sync** - Automated daily updates
✅ **Performance Metrics** - Open rate, click rate, engagement level
✅ **Scalable** - Handles large campaign libraries

**Next Step**: Run `php artisan cm:import-campaign-stats` to import your first campaigns!

---

*Created: November 11, 2024*
*Status: Production Ready ✅*
*Framework: Laravel 12.35 + Campaign Monitor PHP SDK 7.1*
