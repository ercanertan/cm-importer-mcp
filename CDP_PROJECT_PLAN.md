# B2B Multi-Org CDP with Campaign Monitor Integration - Project Plan

**Date Created:** 2025-11-12
**Project Type:** Laravel 12 Multi-Tenant B2B Customer Data Platform
**Scale:** 60,000 users across multiple organizations
**Timeline:** ~12 weeks

---

## Executive Summary

Building a comprehensive B2B Multi-Org CDP that manages unlimited subscriber data locally while working within Campaign Monitor's 50 custom field limitation. The solution uses a **HYBRID APPROACH** combining persistent field sync with dynamic tagging to handle both recurring campaigns and complex one-time campaigns efficiently.

---

## Current System Analysis

### What EXISTS:
- ✅ Laravel 12 application with multi-org structure
- ✅ Campaign Monitor CSV import system (no API integration yet)
- ✅ Organization & domain management with conditional rules
- ✅ Custom field storage (unlimited local storage)
- ✅ User-organization relationships (many-to-many)
- ✅ Import/sync logging infrastructure
- ✅ Queue system foundation

### What's MISSING (To Build):
- ❌ Tier system (free/pro/enterprise)
- ❌ Product subscription system (user opt-in/opt-out)
- ❌ Campaign Monitor API integration
- ❌ Bi-directional sync (CDP ↔ CM)
- ❌ Event tracking & behavioral data
- ❌ Activity scoring system
- ❌ Advanced segmentation engine
- ❌ Campaign management UI
- ❌ Recurring campaign scheduler

---

## Architecture: HYBRID APPROACH

### The Challenge
Campaign Monitor limits accounts to **50 custom fields per list**. With unlimited products, events, and behavioral data, we need a solution that:
1. Stores unlimited data in CDP
2. Syncs only essential data to CM
3. Handles daily/weekly/monthly recurring campaigns efficiently
4. Supports complex one-time campaigns
5. Manages tier changes for thousands of users without API overload

### The Solution: Persistent Fields + Dynamic Tags

```
┌─────────────────────────────────────────────────────────────┐
│           CDP (Laravel) - Single Source of Truth             │
├─────────────────────────────────────────────────────────────┤
│  • Organizations + Tiers (free/pro/enterprise)              │
│  • Users + Products + Opt-ins (unlimited)                   │
│  • Events + Attendance Tracking (unlimited)                 │
│  • Activity Scoring (7d, 30d)                               │
│  • Email Activity History (opens, clicks, bounces)          │
│  • Custom Fields (unlimited)                                │
│  • Advanced Segmentation Engine                             │
│  • Campaign Management System                               │
└───────────────┬─────────────────────────────┬───────────────┘
                │                             │
    Persistent Sync (10 fields)   Dynamic Tags (2 fields)
    └── Daily/Weekly delta         └── Ad-hoc campaigns
    └── Tier changes (batched)     └── Complex queries
                │                             │
                ▼                             ▼
┌─────────────────────────────────────────────────────────────┐
│              Campaign Monitor (Email Delivery)               │
├─────────────────────────────────────────────────────────────┤
│  PERSISTENT FIELDS (10 of 50 used):                         │
│  1. tier_name (free/pro/enterprise)                         │
│  2. tier_id (numeric)                                       │
│  3. primary_org_id (organization ID)                        │
│  4. primary_org_name (organization name)                    │
│  5. active_products_count (number of subscribed products)   │
│  6. activity_score_7d (0-100, updated daily)                │
│  7. activity_score_30d (0-100, updated weekly)              │
│  8. last_login_date (last activity date)                    │
│  9. account_status (active/inactive)                        │
│  10. cm_subscriber_id (CDP user ID for webhook matching)    │
│                                                             │
│  DYNAMIC FIELDS (2 of 50 used):                             │
│  11. temp_campaign_tag (for complex one-time campaigns)     │
│  12. temp_segment_tag (backup/alternative tag)              │
│                                                             │
│  TOTAL USED: 12 of 50 fields (38 fields remaining)         │
└─────────────────────────────────────────────────────────────┘
```

---

## Campaign Strategy Matrix

### When to Use Persistent Segments (Recurring Campaigns)

**Use persistent segments for:**
- Daily newsletters
- Weekly digests
- Monthly reports
- Tier-based campaigns (e.g., "Pro tier gets daily, Free tier gets weekly")
- Activity-based campaigns (e.g., "Active last 7 days")
- Any recurring campaign with stable criteria

**How it works:**
1. Fields are synced continuously via delta updates (only changed users)
2. CM segments are created once and auto-update as fields change
3. Campaigns send instantly to pre-existing segments (no tagging step)
4. Zero API calls for segmentation (data already in sync)

**Example:**
```php
// Daily Pro/Enterprise Newsletter
$schedule->call(function() {
    Campaign::sendToSegment('pro_enterprise_daily', [
        'template' => 'daily_newsletter',
        'subject' => 'Your Daily Insights'
    ]);
})->dailyAt('08:00');

// API Calls: 2 (create campaign + send) = 60 calls/month
// Segmentation: 0 calls (segment always current)
```

---

### When to Use Dynamic Tags (One-Time Campaigns)

**Use dynamic tags for:**
- Complex multi-table queries (e.g., "attended Event X + subscribed to Product Y + no purchase in 60 days")
- Event-based campaigns (e.g., "attended specific event 2 years ago")
- Behavioral campaigns requiring JOINs (e.g., "opened Email A, B, C")
- Ad-hoc promotional campaigns with unique criteria

**How it works:**
1. CDP builds complex query (fast, using database JOINs)
2. Resulting users are tagged in CM using temp_campaign_tag field
3. CM segment created based on tag value
4. Campaign sent to tagged segment
5. Tag cleaned up after send (automated background job)

**Example:**
```php
// Complex one-time campaign
$users = User::whereHas('events', function($q) {
        $q->where('name', 'SaaS Summit 2023')
          ->whereBetween('attended_at', ['2023-01-01', '2023-12-31']);
    })
    ->whereHas('productSubscriptions', fn($q) => $q->where('product_id', 5))
    ->whereDoesntHave('purchases', fn($q) => $q->where('created_at', '>', now()->subDays(60)))
    ->get(); // 3,500 users

$tag = 'campaign_event_x_promo_' . now()->timestamp;
Campaign::sendToTag($users, $tag);

// API Calls: 4 (tag) + 2 (create + send) + 4 (cleanup) = 10 calls total
```

---

## Handling Tier Changes Efficiently

### The Challenge
When an organization with 2,000 users changes tiers, all users need their tier_name field updated in Campaign Monitor.

### The Solution: Batched Background Sync

```php
// Tier change event triggered
event(new OrganizationTierChanged($organization, $oldTier, $newTier));

// Background job queued (non-blocking)
SyncOrganizationTierChangeJob::dispatch($organization, $newTier, $userIds)
    ->onQueue('cm-sync');

// User sees instant response: "✓ Upgraded to Pro!"

// Behind the scenes (5-10 seconds later):
// - Queue worker picks up job
// - Batches 2,000 users into chunks of 1,000
// - 2 API calls (1 per 1,000 users)
// - CM segments auto-update
// - Next campaign automatically includes upgraded users

// Result: 2 API calls, 10 seconds, non-blocking
```

**Performance at scale:**
- 500 users = 1 API call
- 2,000 users = 2 API calls
- 5,000 users = 5 API calls
- All processed in background (user doesn't wait)

---

## API Usage Estimates (60K Users)

### Initial Setup (One-Time)
```
Full sync 60K users with 10 persistent fields:
└── 60K ÷ 1000 per batch = 60 API calls (one-time)
```

### Monthly Recurring Operations
```
Daily Newsletter (Pro/Enterprise):
├── Send to persistent segment
├── 30 days × 2 API calls (create + send) = 60 calls/month
└── Segmentation: 0 calls (already synced)

Weekly Digests (3 tier-specific versions):
├── Free tier weekly = 4 weeks × 2 calls = 8 calls/month
├── Pro tier weekly = 4 weeks × 2 calls = 8 calls/month
├── Enterprise weekly = 4 weeks × 2 calls = 8 calls/month
└── Segmentation: 0 calls (already synced)

Monthly Reports (Enterprise):
├── 1 month × 2 API calls = 2 calls/month
└── Segmentation: 0 calls

Daily Activity Score Updates:
├── ~1,000 users with changed scores daily
├── 30 days × 1 call = 30 calls/month
└── Delta sync (only changed users)

Weekly Activity Recalculation:
├── ~3,000 users recalculated weekly
├── 4 weeks × 3 calls = 12 calls/month
└── Full activity refresh

Tier Changes (ad-hoc):
├── ~15 organizations change tiers monthly
├── Average 500 users per org
├── Batched sync: 15 calls/month
└── Background processing

Ad-hoc Complex Campaigns:
├── ~5 campaigns using dynamic tags
├── Average 10 API calls per campaign
└── 50 calls/month

TOTAL: ~193 API calls/month
Daily average: ~6-7 API calls/day (very low!)
```

**Campaign Monitor has no monthly API limit for reasonable usage. This volume is easily within acceptable range.**

---

## Performance & Scale Analysis

### 60K Users is Highly Manageable

**Database Performance:**
- Total dataset: ~650 MB (fits in RAM)
- Complex queries: <1 second (with proper indexes)
- Segment exports: 1-3 seconds for 10K users
- Activity score updates: 2-5 seconds for 60K users (bulk)

**API Sync Performance:**
- Full 60K sync: 6-10 minutes (initial setup only)
- Daily delta sync (1K changed users): 30-60 seconds
- Tier change (2K users): 10 seconds (background)
- Campaign tag (10K users): 30-40 seconds

**Server Requirements:**
- Web: 2-4 CPU cores, 4-8 GB RAM
- Database: 2-4 CPU cores, 4-8 GB RAM
- Queue workers: 3 workers (cm-sync, webhooks, default)
- Redis cache (optional): 1-2 GB RAM
- **Estimated cost: $50-100/month** (single server setup)

**Growth Headroom:**
- 60K users: ✅ Excellent (current)
- 100K users: ✅ Very Good (same infrastructure)
- 250K users: ✅ Good (add Redis cache)
- 500K users: ⚠️ Good (optimize, more workers)
- 1M+ users: ⚠️ Architecture review needed

---

## Database Schema Overview

### New Tables to Create

```sql
-- Tiers
tiers (id, name, slug, price, description, features_json, is_active, created_at, updated_at)

-- Products
products (id, name, slug, description, is_active, created_at, updated_at)

-- Product-Tier Relationship (which products available in which tiers)
product_tier (id, product_id, tier_id, created_at, updated_at)

-- Organization-Tier Assignment
organization_tier (id, organization_id, tier_id, started_at, expires_at, created_at, updated_at)

-- User Product Subscriptions (opt-in/opt-out)
user_product_subscriptions (id, user_id, product_id, is_active, subscribed_at, unsubscribed_at, created_at, updated_at)

-- Events
events (id, name, slug, description, event_date, location, is_active, created_at, updated_at)

-- User Event Attendance
user_events (id, user_id, event_id, attended_at, registration_status, created_at, updated_at)

-- Email Activities (from CM webhooks)
email_activities (id, user_id, campaign_id, activity_type, cm_campaign_id, occurred_at, created_at, updated_at)

-- Campaigns (local tracking)
campaigns (id, name, segment_id, cm_campaign_id, cm_segment_id, campaign_tag, recipient_count, status, sent_at, created_at, updated_at)

-- Segments (saved queries)
segments (id, name, description, rules_json, is_persistent, cm_segment_id, created_at, updated_at)
```

### Existing Tables to Extend

```sql
-- Add to users table
ALTER TABLE users ADD COLUMN activity_score_7d INT DEFAULT 0;
ALTER TABLE users ADD COLUMN activity_score_30d INT DEFAULT 0;
ALTER TABLE users ADD COLUMN last_synced_to_cm_at TIMESTAMP NULL;

-- Add to organizations table
ALTER TABLE organizations ADD COLUMN cm_synced_at TIMESTAMP NULL;
```

---

## Key Performance Optimizations

### Critical Database Indexes
```sql
-- Users
CREATE INDEX idx_users_cm_subscriber_id ON users(cm_subscriber_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_activity_score_7d ON users(activity_score_7d);
CREATE INDEX idx_users_activity_score_30d ON users(activity_score_30d);
CREATE INDEX idx_users_last_login ON users(last_login_at);

-- User Product Subscriptions
CREATE INDEX idx_user_product_subscriptions_user_product ON user_product_subscriptions(user_id, product_id);
CREATE INDEX idx_user_product_subscriptions_active ON user_product_subscriptions(is_active, product_id);

-- User Events
CREATE INDEX idx_user_events_user_event ON user_events(user_id, event_id);
CREATE INDEX idx_user_events_attended ON user_events(attended_at);

-- Email Activities
CREATE INDEX idx_email_activities_user_created ON email_activities(user_id, created_at);
CREATE INDEX idx_email_activities_campaign ON email_activities(campaign_id, activity_type);

-- Custom Field Values (existing)
CREATE INDEX idx_cm_custom_field_values_user_field ON cm_custom_field_values(user_id, cm_custom_field_id);
```

### Caching Strategy
```php
// Cache segment results (1 hour)
Cache::remember('segment_active_7d', 3600, fn() =>
    User::where('activity_score_7d', '>=', 70)->pluck('id')
);

// Cache user product counts (on change)
Cache::tags(['user:' . $userId])->remember('product_count', fn() =>
    $user->activeProductSubscriptions()->count()
);

// Cache tier info (24 hours)
Cache::remember('org_tier:' . $orgId, 86400, fn() =>
    Organization::with('tier')->find($orgId)
);
```

---

## Implementation Phases

### Phase 1: Foundation (Weeks 1-2)
- Database schema design
- Migrations (tiers, products, subscriptions)
- Models & relationships
- User opt-in/opt-out system
- Basic admin UI

### Phase 2: Event & Activity Tracking (Weeks 3-4)
- Event system (events, attendance)
- Activity scoring engine (7d, 30d scores)
- Email activity tracking models
- Scoring calculation jobs

### Phase 3: Segmentation Engine (Weeks 5-6)
- Segment builder (query builder)
- Persistent vs dynamic logic
- Segment preview/testing
- Caching layer

### Phase 4: CM API Integration (Weeks 7-9)
- Install createsend-php package
- Build CM API service wrapper
- Persistent field sync (delta updates)
- Dynamic tag service
- Webhook handlers
- Background sync jobs
- Error handling & retries

### Phase 5: Campaign Management (Weeks 10-11)
- Campaign builder UI
- Segment routing logic (persistent vs tag)
- Schedule recurring campaigns
- Campaign monitoring
- Tag cleanup automation

### Phase 6: Monitoring & Polish (Week 12)
- Sync monitoring dashboard
- API usage tracking
- Performance optimization
- Testing & QA
- Documentation

---

## Todo List (40 Tasks)

See the TodoWrite tool output for the complete, up-to-date task list. Key highlights:

**Foundation (7 tasks):**
- Design database schema
- Create migrations (tiers, products)
- Create models with relationships
- Build opt-in/opt-out system

**Tracking Systems (2 tasks):**
- Event tracking system
- Activity scoring system

**Segmentation (1 task):**
- Advanced segmentation engine

**CM Integration (11 tasks):**
- API client integration
- Hybrid field mapping (10 persistent + 2 dynamic)
- Persistent field sync with delta updates
- Dynamic tag service
- Tier change sync (batched, queued)
- Activity score update jobs
- Webhook handlers
- Segment management (persistent + dynamic)
- Campaign routing logic
- Tag cleanup automation

**Infrastructure (5 tasks):**
- Queue system configuration
- Audit logging
- Error handling & retries
- Caching implementation
- Database indexes

**UI/UX (6 tasks):**
- Admin dashboard (tier/product management)
- User dashboard (opt-in/opt-out)
- Segment builder UI
- Campaign management interface
- Scheduled campaign system
- Sync monitoring dashboard

**Testing & Documentation (5 tasks):**
- Automated tests (tier/product, sync, segmentation)
- Field mapping documentation
- User guides
- Admin guides
- Data migration plan

**Operations (3 tasks):**
- Queue worker setup
- Initial 60K user migration
- Performance tuning

---

## Key Design Decisions

### Decision 1: Hybrid Approach (Persistent + Dynamic)
**Why:** Balances efficiency (recurring campaigns use persistent segments) with flexibility (complex campaigns use dynamic tags). Minimizes API calls while maintaining unlimited segmentation power.

### Decision 2: Only 12 of 50 CM Fields Used
**Why:** Keeps 76% of CM fields available for future needs. All other data stays in CDP where it's unlimited and queryable.

### Decision 3: Background Queue for Tier Changes
**Why:** Tier changes can affect thousands of users. Queued background processing provides instant user response while syncing efficiently in batches.

### Decision 4: Delta Sync for Activity Scores
**Why:** Only 1,000-3,000 users (1.6-5%) change activity scores daily. Syncing only changed users reduces API calls by 95%+.

### Decision 5: Cleanup Dynamic Tags
**Why:** Keeps temp fields clean for next campaign. Prevents field pollution and confusion in CM interface.

### Decision 6: CDP as Source of Truth
**Why:** Unlimited storage, complex queries, full audit trail. CM is purely for email delivery.

---

## Success Criteria

### Technical Success:
- ✅ All 60K users synced to CM with 10 persistent fields
- ✅ Recurring campaigns send with zero segmentation API calls
- ✅ Complex campaigns execute in <2 minutes (query + tag + send)
- ✅ Tier changes sync in background (<30 seconds for 2K users)
- ✅ Daily operations require <10 API calls/day average
- ✅ Database queries complete in <1 second
- ✅ Zero manual CM interface work (except templates)

### Business Success:
- ✅ Unlimited products/events trackable
- ✅ Unlimited custom fields storable
- ✅ Complex segmentation queries possible
- ✅ Full audit trail maintained
- ✅ Tier-based campaign frequencies working
- ✅ User opt-in/opt-out functional
- ✅ Campaign sends automated

---

## Risk Mitigation

### Risk 1: CM API Rate Limits
**Mitigation:** Queue system with rate limiting (1 call/second), chunked batching (1K users/call), delta sync (only changed users)

### Risk 2: Data Sync Failures
**Mitigation:** Retry logic with exponential backoff, comprehensive error logging, sync monitoring dashboard, manual resync capability

### Risk 3: Performance Degradation
**Mitigation:** Database indexes from day 1, Redis caching, query optimization, chunked processing, separate queue workers

### Risk 4: Complex Query Performance
**Mitigation:** Segment result caching, pre-computed scores, optimized JOINs, proper indexing, query profiling

### Risk 5: Tier Change Surge
**Mitigation:** Queue system handles bursts, batched processing, prioritized queue (tier changes on separate queue), rate limiting

---

## Next Steps

1. **Review this document** with stakeholders
2. **Confirm approach** (Hybrid persistent + dynamic)
3. **Prioritize features** (any must-haves for Phase 1?)
4. **Begin Phase 1** (database schema + migrations)
5. **Setup development environment** (queues, Redis optional)

---

## Notes & Considerations

- **CM Templates:** Managed in CM interface (as requested)
- **Webhooks:** Require publicly accessible URL (development: use ngrok or similar)
- **Testing:** Test with small user subset before full 60K sync
- **Migration:** Plan maintenance window for initial sync (optional, can run in background)
- **Monitoring:** CloudWatch/Papertrail for queue monitoring recommended
- **Backup:** Regular database backups before major syncs

---

## References

- Campaign Monitor API Docs: https://www.campaignmonitor.com/api/v3-3/
- CM createsend-php Package: https://github.com/campaignmonitor/createsend-php
- Laravel Queues: https://laravel.com/docs/12.x/queues
- Laravel Events: https://laravel.com/docs/12.x/events

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Status:** Ready for Implementation
