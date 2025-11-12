# CDP Workflow Diagrams - Historical Reference

**Date Created:** 2025-11-12
**Document Version:** 1.0
**Purpose:** Visual diagrams for planning and review
**Related Documents:** TODO_LIST.md, CDP_WORKFLOWS.md, CDP_UI_SPECIFICATIONS.md

This document contains all workflow diagrams for the B2B Multi-Org CDP system with Campaign Monitor integration.

---

## Table of Contents

1. [System Architecture Overview](#system-architecture-overview)
2. [Workflow #1: Recurring Campaigns](#workflow-1-recurring-campaigns-cdp-triggered)
3. [Workflow #2: New User Registration Sync](#workflow-2-new-user-registration-sync)
4. [Workflow #3: Complex Segmented Campaigns](#workflow-3-complex-segmented-campaigns-brain--voice)
5. [Segment Type Decision Tree](#segment-type-decision-tree)
6. [Data Sync Schedule](#data-sync-schedule-persistent-fields)
7. [Database Schema ERD](#database-schema-erd)
8. [Queue Architecture](#queue-architecture)
9. [Livewire Component Interaction](#livewire-component-interaction)
10. [Campaign Metrics Fetch Workflow](#campaign-metrics-fetch-workflow)
11. [Backfill Operations Architecture](#backfill-operations-architecture)
12. [Re-Sync Tool Workflow](#re-sync-tool-workflow)

---

## System Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        CDP SYSTEM ARCHITECTURE                   │
└─────────────────────────────────────────────────────────────────┘

┌──────────────────────┐
│   USER INTERFACES    │
├──────────────────────┤
│                      │
│  Admin Dashboard     │◄─────┐
│  ├─ Tier Manager     │      │
│  ├─ Product Manager  │      │
│  ├─ Segment Builder  │      │
│  ├─ Campaign Manager │      │
│  └─ Sync Status      │      │
│                      │      │
│  User Dashboard      │      │
│  ├─ Product Opt-in   │      │
│  └─ Activity View    │      │
└──────────────────────┘      │
         │                    │
         │ Livewire 3.6       │
         │ + Flux Pro         │
         ▼                    │
┌──────────────────────┐      │
│   LARAVEL 12 CDP     │      │
│   (BRAIN)            │      │
├──────────────────────┤      │
│                      │      │
│  Models              │      │
│  ├─ Tier             │      │
│  ├─ Product          │      │
│  ├─ Segment          │      │
│  ├─ Campaign         │      │
│  └─ User             │      │
│                      │      │
│  Services            │      │
│  ├─ ActivityScoring  │      │
│  ├─ SegmentQuery     │      │
│  ├─ DynamicTag       │      │
│  └─ CM Service ──────┼──────┘
│                      │
│  Jobs (Queued)       │
│  ├─ SyncPersistent   │
│  ├─ AddSubscriber    │
│  ├─ TagAndSend       │
│  └─ CleanupTag       │
└──────────────────────┘
         │
         │ API Calls
         ▼
┌──────────────────────┐
│  CAMPAIGN MONITOR    │
│  (VOICE)             │
├──────────────────────┤
│                      │
│  ├─ Subscribers      │
│  ├─ Custom Fields    │
│  │   ├─ Persistent  │
│  │   └─ Dynamic     │
│  ├─ Segments         │
│  └─ Campaigns        │
│                      │
│  Webhooks ───────────┼──┐
│  ├─ Opens            │  │
│  ├─ Clicks           │  │
│  └─ Unsubscribes     │  │
└──────────────────────┘  │
                          │
         ┌────────────────┘
         │
         ▼
┌──────────────────────┐
│   EMAIL DELIVERY     │
│   Recipients         │
└──────────────────────┘
```

---

## Workflow #1: Recurring Campaigns (CDP-Triggered)

```
┌───────────────────────────────────────────────────────────────────┐
│           RECURRING CAMPAIGNS WORKFLOW (DAILY/WEEKLY/MONTHLY)     │
└───────────────────────────────────────────────────────────────────┘

┌─────────────────────┐
│  Laravel Scheduler  │  ◄──── Cron: * * * * * (every minute)
│  (app/Console/      │
│   Kernel.php)       │
└──────────┬──────────┘
           │
           │ Daily at 8:00 AM
           │ Weekly on Friday 9:00 AM
           │ Monthly on 1st at 10:00 AM
           ▼
┌─────────────────────┐
│ SendRecurring       │
│ CampaignJob         │
│                     │
│ Queue: cm-campaigns │
│ Tries: 3            │
│ Timeout: 60s        │
└──────────┬──────────┘
           │
           ├──► 1. Get segment (tier-based)
           │    ├─ Free Tier Weekly
           │    ├─ Pro Tier Daily
           │    └─ Enterprise Monthly
           │
           ├──► 2. Get template
           │    └─ CM Template ID + Subject
           │
           ├──► 3. Create campaign in CM
           │    └─ API Call #1
           │         POST /campaigns
           │         ├─ Subject: "Daily News - Nov 12, 2025"
           │         ├─ Template ID
           │         └─ Segment IDs (persistent)
           │
           ├──► 4. Send campaign
           │    └─ API Call #2
           │         POST /campaigns/{id}/send
           │         └─ SendDate: immediately
           │
           └──► 5. Track in CDP
                └─ Save to campaigns table
                    ├─ cm_campaign_id
                    ├─ recipient_count
                    ├─ sent_at
                    └─ status: 'sent'

┌─────────────────────────────────────────────────────────────────┐
│  UI: CampaignTemplateManager                                     │
│  Route: /admin/cdp/campaign-templates                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Active Templates                 Next Send: Fri 9:00 AM │  │
│  │  ├─ Daily Newsletter (Pro)        ⏱ 2h 15m remaining     │  │
│  │  ├─ Weekly Digest (Free)          Status: Active ✓       │  │
│  │  └─ Monthly Report (Enterprise)   [Edit] [Pause] [Send]  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
│  [Create Template] [View Campaign History]                      │
└─────────────────────────────────────────────────────────────────┘

API Usage: 2 calls per send × 4 templates × 30 days = 86 calls/month
```

---

## Workflow #2: New User Registration Sync

```
┌───────────────────────────────────────────────────────────────────┐
│              NEW USER REGISTRATION SYNC WORKFLOW                  │
└───────────────────────────────────────────────────────────────────┘

USER BROWSER                 LARAVEL CDP                 CAMPAIGN MONITOR
─────────────                ───────────                 ────────────────

    │                            │                              │
    │  POST /register            │                              │
    │  ├─ name: Jane Doe         │                              │
    │  ├─ email: jane@org.com    │                              │
    │  ├─ password               │                              │
    │  └─ consent: true          │                              │
    ├───────────────────────────►│                              │
    │                            │                              │
    │                            │  RegisterController          │
    │                            │  ├─ Validate data            │
    │                            │  ├─ Create organization      │
    │                            │  ├─ Create user              │
    │                            │  │   ├─ id: 123              │
    │                            │  │   ├─ tier: free           │
    │                            │  │   ├─ activity_7d: 0       │
    │                            │  │   └─ cm_status: null      │
    │                            │  └─ Attach to org            │
    │                            │                              │
    │  ✓ Registration Success!   │                              │
    │◄───────────────────────────┤                              │
    │  (Instant response)        │                              │
    │                            │                              │
    │                            │  Event::dispatch             │
    │                            │  UserWasRegistered           │
    │                            │       │                      │
    │                            │       ▼                      │
    │                            │  Listener:                   │
    │                            │  SyncUserToCampaignMonitor   │
    │                            │       │                      │
    │                            │       ▼                      │
    │                            │  AddSubscriberToCmJob        │
    │                            │  Queue: cm-sync              │
    │                            │  Delay: ~2-5 seconds         │
    │                            │       │                      │
    │                            │       │  1. Check existing   │
    │                            │       │     subscriber       │
    │                            │       │────────────────────► │
    │                            │       │  GET /subscribers/   │
    │                            │       │      {email}         │
    │                            │       │                      │
    │                            │       │  ◄──────────────────│
    │                            │       │  404 Not Found       │
    │                            │       │                      │
    │                            │       │  2. Add subscriber   │
    │                            │       │────────────────────► │
    │                            │       │  POST /subscribers   │
    │                            │       │  {                   │
    │                            │       │   email: jane@..     │
    │                            │       │   name: Jane Doe     │
    │                            │       │   CustomFields: [    │
    │                            │       │     tier_name: free  │
    │                            │       │     tier_id: 1       │
    │                            │       │     org_id: 1        │
    │                            │       │     activity_7d: 0   │
    │                            │       │   ]                  │
    │                            │       │   ConsentToTrack: Yes│
    │                            │       │  }                   │
    │                            │       │                      │
    │                            │       │  ◄──────────────────│
    │                            │       │  201 Created         │
    │                            │       │                      │
    │                            │       │  3. Update user      │
    │                            │       ▼                      │
    │                            │  UPDATE users                │
    │                            │  SET cm_synced_at = NOW()    │
    │                            │      cm_status = 'active'    │
    │                            │                              │

┌─────────────────────────────────────────────────────────────────┐
│  Background: Every 2-5 seconds after registration              │
│  User becomes available in CM for campaigns                     │
│  Persistent fields auto-update nightly (SyncPersistentFieldsJob)│
└─────────────────────────────────────────────────────────────────┘

API Usage: 2 calls per registration (check + add)
           100 registrations/day = 200 calls/day = 6,000 calls/month
```

---

## Workflow #3: Complex Segmented Campaigns (Brain & Voice)

```
┌───────────────────────────────────────────────────────────────────┐
│          COMPLEX SEGMENTED CAMPAIGN WORKFLOW (DYNAMIC TAG)        │
└───────────────────────────────────────────────────────────────────┘

ADMIN UI                    LARAVEL CDP                  CAMPAIGN MONITOR
────────                    ───────────                  ────────────────

┌──────────────────┐
│ Segment Builder  │
│ /admin/cdp/      │
│  segments/builder│
└────────┬─────────┘
         │
         │  1. Admin builds segment
         │     Rules:
         │     ├─ tier IN [pro, enterprise]
         │     ├─ product_id = 5 (subscribed)
         │     └─ attended "2024 Webinar"
         │
         ▼
┌──────────────────┐
│ Live Preview     │       SegmentQueryBuilder
│ Panel            │       ├─ Execute complex query
│ ─────────────    │       ├─ JOINs: users + orgs +
│ Matching Users:  │       │        products + events
│ 2,345            │◄──────┤ Result: 2,345 users
│                  │       └─ Query time: 0.5-2s
│ Sample (10):     │
│ ├─ John (Pro)    │       Segment Type Resolver
│ ├─ Jane (Ent)    │       ├─ Check complexity
│ └─ ...           │       └─ Decision: DYNAMIC TAG
└────────┬─────────┘              (complex JOINs detected)
         │
         │  2. Admin saves segment
         ▼
┌──────────────────┐
│ Campaign Builder │
│ Multi-Step       │
└────────┬─────────┘
         │
         │  Step 1: Select Segment
         │  └─ "2024 Webinar Attendees - Pro/Ent"
         │
         │  Step 2: Configure Campaign
         │  ├─ Name: "Webinar Follow-up"
         │  ├─ Subject: "Thanks for attending!"
         │  └─ CM Template ID
         │
         │  Step 3: Preview
         │  └─ Shows 2,345 recipients
         │      Type: Dynamic Tag 🏷️
         │      Cleanup: 2 hours after send
         │
         │  Step 4: Confirm & Send
         ▼
┌──────────────────┐
│ [SEND NOW] ✓     │
└────────┬─────────┘
         │
         │  Job Dispatched
         ▼
    TagAndSendCampaignJob
    Queue: cm-campaigns
    Timeout: 5 minutes
         │
         ├──► 1. Generate unique tag
         │    └─ "seg_20251112_143022_webinar"
         │
         ├──► 2. Tag users in CM (bulk)────────────────►  BULK IMPORT #1
         │    Batch 1 (1,000 users)                       POST /subscribers/import
         │    ├─ email: john@...                          {
         │    └─ temp_campaign_tag: seg_20251112...         Subscribers: [...]
         │                                                   CustomFields: [
         ├──► 3. Tag users batch 2 ────────────────────►      temp_campaign_tag:
         │    (1,000 users)                                    "seg_20251112..."
         │                                                   ]
         ├──► 4. Tag users batch 3 ────────────────────►  }
         │    (345 users)
         │                                               BULK IMPORT #2
         │                                               BULK IMPORT #3
         │                                                     │
         ├──► 5. Create CM segment ─────────────────────►  CREATE SEGMENT
         │    └─ Rule: temp_campaign_tag                 POST /segments
         │       EQUALS "seg_20251112..."                {
         │                                                 Title: "Webinar Follow-up"
         │                                                 Rules: [
         │                                                   temp_campaign_tag
         │                                                   EQUALS "seg_..."
         │                                                 ]
         │                                               }
         │                                                     │
         │                                               ◄─────┘
         │                                               segment_id: ABC123
         │
         ├──► 6. Create campaign ───────────────────────►  CREATE CAMPAIGN
         │    └─ Use segment_id                          POST /campaigns
         │                                               {
         │                                                 Name: "Webinar Follow-up"
         │                                                 Subject: "Thanks..."
         │                                                 TemplateID: ...
         │                                                 SegmentIDs: [ABC123]
         │                                               }
         │                                                     │
         ├──► 7. Send campaign ─────────────────────────►  SEND CAMPAIGN
         │    └─ Immediate send                          POST /campaigns/{id}/send
         │                                               {
         │                                                 SendDate: "immediately"
         │                                               }
         │                                                     │
         │                                               ◄─────┴─────┐
         │                                               ✓ Sent!      │
         │                                                            │
         ├──► 8. Track in CDP                                        │
         │    Save to campaigns table:                               │
         │    ├─ cm_campaign_id                                      │
         │    ├─ cm_segment_id: ABC123                               │
         │    ├─ campaign_tag: seg_20251112...                       │
         │    ├─ recipient_count: 2,345                              │
         │    └─ sent_at: NOW()                                      │
         │                                                            │
         └──► 9. Schedule cleanup ───────────────►  CleanupCampaignTagJob
              └─ Delay: 2 hours                   Queue: cm-cleanup
                                                  Delay: 2 hours
                                                       │
              ┌────────────────────────────────────────┘
              │  (2 hours later)
              │
              ├──► 1. Clear tags (bulk) ──────────────►  BULK IMPORT
              │    Batch all 2,345 users               POST /subscribers/import
              │    └─ temp_campaign_tag = ""           {
              │                                          Subscribers: [...]
              │                                          CustomFields: [
              ├──► 2. Delete CM segment ──────────────►    temp_campaign_tag: ""
              │    └─ DELETE segment_id                  ]
              │                                        }
              │                                        DELETE /segments/ABC123
              └──► 3. Mark cleanup complete
                   UPDATE campaigns
                   SET cleanup_completed_at = NOW()

┌─────────────────────────────────────────────────────────────────┐
│  Total API Calls: 7 calls (3 tag + 1 segment + 2 send + 1 del) │
│  Execution Time: ~10 seconds for 2,000 users                   │
│  Campaign Status: Viewable in CampaignDetail component         │
└─────────────────────────────────────────────────────────────────┘
```

---

## Segment Type Decision Tree

```
┌─────────────────────────────────────────────────────────────────┐
│          SEGMENT TYPE DECISION LOGIC (Brain & Voice Pattern)    │
└─────────────────────────────────────────────────────────────────┘

                    Admin Creates Segment
                            │
                            ▼
                    Analyze Query Rules
                            │
                ┌───────────┴───────────┐
                │                       │
         Simple Rules?           Complex Rules?
         ├─ tier_name            ├─ Event attendance
         ├─ activity_score       ├─ Product subscriptions ⚠️ CRITICAL
         └─ last_login           ├─ Multiple JOINs
                │                └─ Behavioral data
                │                       │
                ▼                       ▼
        ┌─────────────────┐    ┌─────────────────┐
        │ PERSISTENT      │    │ DYNAMIC TAG     │
        │ SEGMENT         │    │ SEGMENT         │
        ├─────────────────┤    ├─────────────────┤
        │                 │    │                 │
        │ ✅ Created in CM │    │ ✅ Query in CDP  │
        │ ✅ Auto-updated  │    │ ✅ Tag on send   │
        │    nightly      │    │ ✅ Cleanup 2h    │
        │ ✅ 0 API calls   │    │ ❌ Temp segments │
        │    per campaign │    │                 │
        │ ✅ Ideal for     │    │ ✅ Ideal for     │
        │    recurring    │    │    one-off      │
        │                 │    │    complex      │
        │ Examples:       │    │                 │
        │ ├─ Free tier    │    │ Examples:       │
        │ ├─ Pro tier     │    │ ├─ Webinar      │
        │ └─ High activity│    │ │   attendees   │
        │                 │    │ ├─ Multi-product│
        │                 │    │ │   users       │
        │                 │    │ └─ Event-based  │
        │                 │    │     targeting   │
        └─────────────────┘    └─────────────────┘
```

---

## Data Sync Schedule (Persistent Fields)

```
┌───────────────────────────────────────────────────────────────────┐
│              NIGHTLY SYNC SCHEDULE (Persistent Fields)            │
└───────────────────────────────────────────────────────────────────┘

TIME        JOB                        WHAT SYNCS              API CALLS
────        ───                        ──────────              ─────────

2:00 AM  ► UpdateActivityScoresJob    Calculate:              0
           Queue: cdp-analytics        ├─ activity_score_7d    (CDP only)
           └─ Updates users table      └─ activity_score_30d

3:00 AM  ► SyncPersistentFieldsJob    Sync to CM:             ~30-60
           Queue: cm-sync              ├─ tier_name            (batched)
           Delta sync only             ├─ tier_id
           └─ Changed users only       ├─ activity_score_7d
                                       ├─ activity_score_30d
                                       ├─ last_login_date
                                       └─ active_products_count

4:00 AM  ► RefreshCMSegmentsJob       Update CM segments      0
           └─ Segments auto-refresh    based on new field      (automatic)
              based on persistent      values
              field changes

Daily    ► Recurring Campaigns        Send scheduled          2 per send
8:00 AM    (as configured)            campaigns using
9:00 AM    └─ Use persistent          persistent segments
Etc.          segments (no extra
              segmentation calls)
```

---

## Database Schema ERD

```
┌───────────────────────────────────────────────────────────────────┐
│                    DATABASE SCHEMA (ERD)                          │
└───────────────────────────────────────────────────────────────────┘

┌─────────────────────┐
│       tiers         │
├─────────────────────┤
│ id (PK)             │
│ name                │──┐
│ slug                │  │
│ description         │  │
│ max_users           │  │
│ features (JSON)     │  │
│ is_active           │  │
│ created_at          │  │
│ updated_at          │  │
└─────────────────────┘  │
                         │
                         │ 1:M
                         │
┌─────────────────────┐  │
│   organizations     │  │
├─────────────────────┤  │
│ id (PK)             │  │
│ name                │  │
│ description         │  │
│ tier_id (FK) ───────┼──┘
│ conditional_rules   │
│ is_active           │
│ created_at          │
│ updated_at          │
└─────────────────────┘
         │
         │ M:M (pivot: organization_user)
         │
         ▼
┌─────────────────────┐          ┌─────────────────────┐
│   organization_     │          │   products          │
│        user         │          ├─────────────────────┤
├─────────────────────┤          │ id (PK)             │
│ user_id (FK)        │──┐       │ name                │
│ organization_id (FK)│  │       │ slug                │
│ is_primary          │  │       │ description         │
│ is_manual           │  │       │ is_active           │
│ created_at          │  │       │ created_at          │
│ updated_at          │  │       │ updated_at          │
└─────────────────────┘  │       └─────────────────────┘
                         │                │
                         │                │
                         │                │ M:M (pivot: product_tier)
                         │                │
┌─────────────────────┐  │       ┌─────────────────────┐
│       users         │  │       │   product_tier      │
├─────────────────────┤  │       ├─────────────────────┤
│ id (PK)             │◄─┘       │ product_id (FK)     │
│ fullname            │          │ tier_id (FK)        │
│ email (unique)      │          │ created_at          │
│ password            │          │ updated_at          │
│ organization_id (FK)│          └─────────────────────┘
│ permission_to_track │
│ activity_score_7d   │
│ activity_score_30d  │
│ last_login_at       │
│ cm_subscriber_id    │
│ cm_status           │
│ cm_synced_at        │
│ created_at          │
│ updated_at          │
└─────────────────────┘
         │
         │ M:M (pivot: user_product_subscription)
         │
         ▼
┌─────────────────────┐
│ user_product_       │
│   subscription      │
├─────────────────────┤
│ id (PK)             │
│ user_id (FK)        │
│ product_id (FK)     │
│ is_active           │
│ subscribed_at       │
│ unsubscribed_at     │
│ created_at          │
│ updated_at          │
└─────────────────────┘


┌─────────────────────┐          ┌─────────────────────┐
│      segments       │          │ campaign_templates  │
├─────────────────────┤          ├─────────────────────┤
│ id (PK)             │          │ id (PK)             │
│ name                │          │ name                │
│ description         │          │ cm_template_id      │
│ rules (JSON)        │──┐       │ subject             │
│ type (enum)         │  │       │ type (enum)         │
│ is_active           │  │       │ schedule_config     │
│ cm_segment_id       │  │       │ is_active           │
│ created_at          │  │       │ next_send_at        │
│ updated_at          │  │       │ last_sent_at        │
└─────────────────────┘  │       │ created_at          │
                         │       │ updated_at          │
                         │       └─────────────────────┘
                         │
                         │ 1:M
                         │
┌─────────────────────┐  │
│     campaigns       │  │
├─────────────────────┤  │
│ id (PK)             │  │
│ name                │  │
│ type (enum)         │  │
│ segment_id (FK) ────┼──┘
│ template_id (FK)    │
│ cm_campaign_id      │
│ cm_segment_id       │
│ campaign_tag        │
│ recipient_count     │
│ status (enum)       │
│ sent_at             │
│ cleanup_completed_at│
│ metadata (JSON)     │
│ created_at          │
│ updated_at          │
└─────────────────────┘


┌─────────────────────┐          ┌─────────────────────┐
│       events        │          │  event_attendances  │
├─────────────────────┤          ├─────────────────────┤
│ id (PK)             │          │ id (PK)             │
│ name                │──┐       │ user_id (FK)        │
│ description         │  │ 1:M   │ event_id (FK) ──────┼──┐
│ event_date          │  │       │ attended_at         │  │
│ location            │  │       │ created_at          │  │
│ is_active           │  │       │ updated_at          │  │
│ created_at          │  │       └─────────────────────┘  │
│ updated_at          │  │                                 │
└─────────────────────┘  │                                 │
                         └─────────────────────────────────┘


┌─────────────────────┐
│    audit_logs       │
├─────────────────────┤
│ id (PK)             │
│ user_id (FK)        │
│ action              │
│ model               │
│ model_id            │
│ before (JSON)       │
│ after (JSON)        │
│ ip_address          │
│ user_agent          │
│ created_at          │
└─────────────────────┘


┌─────────────────────┐
│  cm_webhook_logs    │
├─────────────────────┤
│ id (PK)             │
│ user_id (FK)        │
│ event_type          │
│ campaign_id         │
│ payload (JSON)      │
│ processed_at        │
│ created_at          │
└─────────────────────┘
```

---

## Queue Architecture

```
┌───────────────────────────────────────────────────────────────────┐
│                       QUEUE ARCHITECTURE                          │
└───────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                         SUPERVISOR                               │
│                    (Process Manager)                             │
└─────────────────────────────────────────────────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
        ▼                     ▼                     ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│ cm-sync      │    │ cm-campaigns │    │ cm-cleanup   │
│ (Priority 1) │    │ (Priority 2) │    │ (Priority 3) │
├──────────────┤    ├──────────────┤    ├──────────────┤
│ 3 Workers    │    │ 2 Workers    │    │ 1 Worker     │
└──────────────┘    └──────────────┘    └──────────────┘
        │                     │                     │
        │                     │                     │
        ▼                     ▼                     ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│ Jobs:        │    │ Jobs:        │    │ Jobs:        │
│              │    │              │    │              │
│ • AddSub     │    │ • SendRecur  │    │ • CleanupTag │
│   scriberTo  │    │   ringCampai │    │              │
│   Cm         │    │   gn         │    │              │
│              │    │              │    │              │
│ • SyncPersis │    │ • TagAndSend │    │              │
│   tentFields │    │   Campaign   │    │              │
│              │    │              │    │              │
│ • SyncTier   │    │              │    │              │
│   Change     │    │              │    │              │
│              │    │              │    │              │
│ Retry: 3x    │    │ Retry: 3x    │    │ Retry: 3x    │
│ Backoff:     │    │ Timeout: 60s │    │ Timeout: 180s│
│ 1m, 5m, 15m  │    │              │    │              │
└──────────────┘    └──────────────┘    └──────────────┘


┌──────────────┐    ┌──────────────┐
│ webhooks     │    │ default      │
│ (Priority 1) │    │ (Priority 4) │
├──────────────┤    ├──────────────┤
│ 2 Workers    │    │ 2 Workers    │
└──────────────┘    └──────────────┘
        │                     │
        ▼                     ▼
┌──────────────┐    ┌──────────────┐
│ Jobs:        │    │ Jobs:        │
│              │    │              │
│ • ProcessCM  │    │ • General    │
│   Webhook    │    │   Background │
│              │    │   Jobs       │
│ • HandleOpen │    │              │
│              │    │              │
│ • HandleClick│    │              │
│              │    │              │
│ Retry: 3x    │    │ Retry: 3x    │
│ Timeout: 30s │    │ Timeout: 90s │
└──────────────┘    └──────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  Queue Monitoring Dashboard (SyncStatusDashboard)               │
│  ├─ Jobs in queue per queue                                     │
│  ├─ Jobs processing (real-time)                                 │
│  ├─ Jobs failed (last 24h)                                      │
│  ├─ Average wait time                                           │
│  └─ Worker health status                                        │
└─────────────────────────────────────────────────────────────────┘
```

---

## Livewire Component Interaction

```
┌───────────────────────────────────────────────────────────────────┐
│              LIVEWIRE COMPONENT INTERACTION FLOW                  │
│              (Example: Segment Builder → Campaign Send)           │
└───────────────────────────────────────────────────────────────────┘

USER BROWSER                    LIVEWIRE COMPONENTS               BACKEND
────────────                    ───────────────────               ───────

    │
    │  1. Navigate to Segment Builder
    │
    ├──────────────────────────►  SegmentBuilder.php
    │                             ├─ mount()
    │                             ├─ $ruleGroups = []
    │                             ├─ $matchingUsersCount = 0
    │                             └─ render()
    │
    │  ◄──────────────────────────  View: segment-builder.blade.php
    │  (HTML + Alpine.js)           ├─ Rule builder form
    │                                ├─ Live preview panel
    │                                └─ wire:model.live bindings
    │
    │  2. User adds rule
    │     (tier_name = 'pro')
    │
    ├─ wire:model.live ─────────►  SegmentBuilder.php
    │                             ├─ updated($property)
    │                             ├─ debounce: 1s
    │                             └─ refreshPreview()
    │                                     │
    │                                     ├──► SegmentQueryBuilder
    │                                     │    ::build($ruleGroups)
    │                                     │         │
    │                                     │         ├──► Eloquent Query
    │                                     │         │    (users + orgs)
    │                                     │         │
    │                                     │         └──► count()
    │                                     │              = 2,345 users
    │                                     │
    │                                     └──► get() limit 10
    │                                          = $sampleUsers
    │
    │  ◄──── DOM Update (wire:poll) ───  $matchingUsersCount = 2,345
    │  (Live preview updates)            $sampleUsers = [...]
    │
    │  3. User clicks "Save Segment"
    │
    ├─ wire:click="saveSegment" ──►  SegmentBuilder.php
    │                                ├─ saveSegment()
    │                                │   ├─ validate()
    │                                │   ├─ Segment::create([
    │                                │   │    name, rules, type
    │                                │   │  ])
    │                                │   └─ redirect()
    │                                │
    │  ◄─── Flash Message ────────── session()->flash('success')
    │  ◄─── Redirect ───────────────  wire:navigate to SegmentList
    │
    │  4. Navigate to Campaign Builder
    │
    ├──────────────────────────►  CampaignBuilder.php
    │                             ├─ mount()
    │                             ├─ $currentStep = 1
    │                             ├─ loadSegments()
    │                             └─ render()
    │
    │  ◄──────────────────────────  View: 4-step wizard
    │  (Step indicator + forms)
    │
    │  5. Step 1: Select Segment
    │
    ├─ Select segment dropdown ──►  $selectedSegmentId = 123
    │                               ├─ loadSegmentPreview()
    │                               └─ $nextStepEnabled = true
    │
    │  6. Step 2: Configure Campaign
    │
    ├─ Fill form fields ─────────►  $campaignName = "..."
    │                               $subject = "..."
    │                               $templateId = "..."
    │
    │  7. Step 3: Preview
    │
    ├─ wire:click="nextStep" ────►  CampaignBuilder.php
    │                               ├─ $currentStep = 3
    │                               ├─ loadRecipientPreview()
    │                               │   ├─ Segment::find()
    │                               │   ├─ execute()
    │                               │   └─ count() = 2,345
    │                               └─ render()
    │
    │  ◄──────────────────────────  Preview panel updates
    │  Shows: 2,345 recipients      Sample table renders
    │
    │  8. Step 4: Confirm & Send
    │
    ├─ wire:click="sendCampaign" ►  CampaignBuilder.php
    │                               ├─ validate()
    │                               ├─ $users = segment->execute()
    │                               ├─ TagAndSendCampaignJob
    │                               │   ::dispatch($users, ...)
    │                               │   →onQueue('cm-campaigns')
    │                               │
    │                               ├─ Campaign::create([...])
    │                               └─ redirect to CampaignDetail
    │
    │  ◄─── Success Message ─────── "Campaign queued!"
    │  ◄─── Redirect ──────────────  wire:navigate
    │
    │  9. View Campaign Status
    │
    ├──────────────────────────►  CampaignDetail.php
    │                             ├─ mount($campaignId)
    │                             ├─ loadCampaign()
    │                             └─ render()
    │
    │  ◄─── Real-time updates ────  wire:poll.2s
    │  (Progress: Tagging → Sending → Sent)
    │
    │  Background: TagAndSendCampaignJob
    │              executing in queue
    │                     │
    │                     ├──► Tag users in CM
    │                     ├──► Create CM segment
    │                     ├──► Send campaign
    │                     └──► Update campaign.status
    │
    │  ◄─── Status: "Sent" ───────  (after job completes)
    │  Shows: Opens, Clicks, etc.   wire:poll continues
    │
```

---

## Field Mapping Strategy (HYBRID Approach)

```
┌───────────────────────────────────────────────────────────────────┐
│        CAMPAIGN MONITOR CUSTOM FIELDS (HYBRID STRATEGY)           │
└───────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    PERSISTENT FIELDS (10)                        │
│                  (Auto-synced nightly at 3 AM)                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. cm_subscriber_id    → CDP User ID (link back to CDP)        │
│  2. tier_name           → free / pro / enterprise               │
│  3. tier_id             → 1 / 2 / 3                             │
│  4. primary_org_id      → Organization ID                       │
│  5. primary_org_name    → Organization Name                     │
│  6. active_products_count → Count of subscribed products        │
│  7. activity_score_7d   → Engagement score (last 7 days)        │
│  8. activity_score_30d  → Engagement score (last 30 days)       │
│  9. last_login_date     → YYYY-MM-DD                            │
│ 10. account_status      → active / inactive / suspended         │
│                                                                  │
│  Used for: Persistent segments, recurring campaigns             │
│  Update frequency: Nightly (delta sync)                         │
│  API calls: ~30-60/night (batched, only changed users)          │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                     DYNAMIC TAG FIELDS (2)                       │
│              (Set on-demand for complex campaigns)               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. temp_campaign_tag   → "seg_20251112_143022_webinar"         │
│                           (Generated for each complex campaign)  │
│                           Cleared 2 hours after send             │
│                                                                  │
│  2. temp_campaign_tag_2 → Backup field for concurrent campaigns │
│                           (if needed)                            │
│                                                                  │
│  Used for: Complex segmented campaigns (dynamic tags)           │
│  Update frequency: On-demand (when campaign sent)               │
│  API calls: 2-20 per campaign (depends on user count)           │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

TOTAL CUSTOM FIELDS: 12 (10 persistent + 2 dynamic)
```

---

## Product Subscription Filtering Workflow

**This is a CRITICAL addition for product opt-in/out segmentation capabilities.**

```
┌───────────────────────────────────────────────────────────────────────┐
│        PRODUCT SUBSCRIPTION FILTERING - USER JOURNEY                  │
│        (Dynamic Tag Approach - JOIN with user_product_subscription)   │
└───────────────────────────────────────────────────────────────────────┘

ADMIN UI                         CDP BACKEND                    DATABASE
─────────                        ───────────                    ────────

Step 1: Open Segment Builder
   │
   ├──► Select field:
   │    "Subscribed to Product"
   │         │
   │         ▼
   │    Select operator: ──────────────►  Detect operator change
   │    "In" (multi-select)                     │
   │         │                                  ▼
   │         │                           Show multi-product
   │         │                           checkbox UI
   │         │                                  │
   │         ▼                                  │
   │    Check products:                         │
   │    ☑ Premium Newsletter (ID: 5)           │
   │    ☑ Webinar Access (ID: 8)               │
   │    ☐ VIP Events (ID: 12)                  │
   │         │                                  │
   │         │                                  │
   │    wire:model.live triggers ──────────────┤
   │                                            │
   │                                            ▼
   │                                    Build Eloquent Query:
   │                                    ────────────────────
   │                                    User::whereHas(
   │                                      'productSubscriptions',
   │                                      fn($q) => $q
   │                                        ->whereIn('product_id', [5, 8])
   │                                        ->where('is_active', true)
   │                                    )
   │                                            │
   │                                            ▼
   │                                    Execute with JOINs ──────►  Query:
   │                                    Query time: 0.3-0.7s      ───────
   │                                            │                 SELECT DISTINCT users.*
   │                                            │                 FROM users
   │         ┌──────────────────────────────────┘                 INNER JOIN user_product_subscription
   │         │                                                      ON users.id = ups.user_id
   │         ▼                                                    WHERE ups.product_id IN (5, 8)
   │    Live Preview Updates:                                      AND ups.is_active = 1
   │    ───────────────────
   │    Matching Users: 2,345                                    Using Index:
   │    (4.2% of total users)                                    idx_user_product_active
   │         │
   │         ▼
   │    Sample Users (First 10):
   │    ┌─────────────────────────────────────────┐
   │    │ John Doe (john@example.com) - Pro Tier │
   │    │ ✓ Premium Newsletter                    │
   │    │ ✓ Webinar Access                        │
   │    ├─────────────────────────────────────────┤
   │    │ Jane Smith (jane@example.com) - Ent    │
   │    │ ✓ Premium Newsletter                    │
   │    │ ✓ Webinar Access                        │
   │    │ ✓ VIP Events                            │
   │    └─────────────────────────────────────────┘
   │         │
   │         ▼
   │    Segment Type Badge:
   │    [🔶 Dynamic Tag Segment]
   │    "Users will be tagged when campaign sent"
   │         │
   │         ▼
Step 2: Save Segment
   │
   ├──► Click "Save Segment" ─────────────────►  Save to segments table:
   │         │                                   ────────────────────────
   │         │                                   {
   │         │                                     name: "Premium Subscribers",
   │         │                                     type: "dynamic_tag",
   │         │                                     rules: [
   │         │                                       {
   │         │                                         field: "subscribed_to_product",
   │         │                                         operator: "in",
   │         │                                         value: [5, 8]
   │         │                                       }
   │         │                                     ],
   │         │                                     is_active: true
   │         │                                   }
   │         │
   │         ▼
   │    ✓ Segment Saved
   │    Redirect to Campaign Builder
   │

Step 3: Send Campaign (Using Product-Filtered Segment)
   │
   ├──► Select segment:
   │    "Premium Subscribers"
   │         │
   │         ▼
   │    Preview shows:
   │    • 2,345 recipients
   │    • [🔶 Dynamic Tag]
   │    • Sample users with products
   │         │
   │         ▼
   │    Click "Send Campaign" ───────────────►  Dispatch TagAndSendCampaignJob
   │                                                    │
   │                                                    ▼
   │                                            1. Re-execute query (fresh data)
   │                                               User::whereHas(...)
   │                                                    │
   │                                            2. Generate tag
   │                                               "seg_20251112_153045_premium"
   │                                                    │
   │                                            3. Tag 2,345 users in CM
   │                                               (3 API calls, batched)
   │                                                    │
   │                                            4. Create CM segment
   │                                               Rule: temp_campaign_tag
   │                                                     EQUALS "seg_20251112..."
   │                                                    │
   │                                            5. Send campaign
   │                                               (2 API calls)
   │                                                    │
   │         ┌──────────────────────────────────────────┘
   │         │
   │         ▼
   │    Campaign Sent!
   │    View tracking in CampaignDetail
   │         │
   │         ▼
   │    (2 hours later)
   │    Cleanup Job runs:
   │    • Clear tags
   │    • Delete CM segment
   │    • Mark campaign cleaned

┌───────────────────────────────────────────────────────────────────┐
│  KEY PERFORMANCE METRICS (2,345 users subscribed to 2 products)  │
├───────────────────────────────────────────────────────────────────┤
│  Query Execution Time:     0.4 seconds (with composite indexes)  │
│  Tag Users (CM API):       ~3 seconds (3 batched calls)          │
│  Create Segment (CM API):  ~1 second                             │
│  Send Campaign (CM API):   ~2 seconds                            │
│  Total Campaign Send Time: ~6 seconds                            │
│  Cleanup (delayed 2 hrs):  ~3 seconds                            │
├───────────────────────────────────────────────────────────────────┤
│  WITHOUT Indexes:          5-10 seconds query time ⚠️             │
│  WITH Composite Indexes:   0.2-0.7 seconds query time ✅          │
└───────────────────────────────────────────────────────────────────┘
```

---

## Product Filtering Decision Tree

```
┌───────────────────────────────────────────────────────────────────┐
│             PRODUCT SUBSCRIPTION FILTER TYPE SELECTION            │
└───────────────────────────────────────────────────────────────────┘

                    Admin Selects Field
                            │
                            ▼
                    "Products" Optgroup
                            │
            ┌───────────────┼───────────────┬───────────────┐
            │               │               │               │
            ▼               ▼               ▼               ▼
    ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
    │ Subscribed   │ │ NOT          │ │ Opted Out    │ │ Active       │
    │ to Product   │ │ Subscribed   │ │ of Product   │ │ Products     │
    │              │ │ to Product   │ │              │ │ Count        │
    └──────┬───────┘ └──────┬───────┘ └──────┬───────┘ └──────┬───────┘
           │                │                │                │
           │                │                │                │
           ▼                ▼                ▼                ▼
    ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
    │ USE CASE:    │ │ USE CASE:    │ │ USE CASE:    │ │ USE CASE:    │
    │ Standard     │ │ Upsell       │ │ Win-back     │ │ Power User   │
    │ targeting    │ │ campaigns    │ │ campaigns    │ │ campaigns    │
    └──────┬───────┘ └──────┬───────┘ └──────┬───────┘ └──────┬───────┘
           │                │                │                │
           ▼                ▼                ▼                ▼
    ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
    │ QUERY:       │ │ QUERY:       │ │ QUERY:       │ │ QUERY:       │
    │ whereHas(...)│ │ whereDoesnt  │ │ whereHas(... │ │ has(         │
    │   product_id │ │ Have(...)    │ │   is_active  │ │   'product   │
    │   is_active  │ │   product_id │ │   = false    │ │   Subs',     │
    │   = true     │ │   is_active  │ │   unsub_at   │ │   '>=', 3)   │
    │              │ │   = true     │ │   NOT NULL)  │ │              │
    └──────┬───────┘ └──────┬───────┘ └──────┬───────┘ └──────┬───────┘
           │                │                │                │
           └────────────────┴────────────────┴────────────────┘
                                    │
                                    ▼
                        ALL USE DYNAMIC TAG APPROACH
                        (Requires JOIN, cannot use
                         persistent CM segments)
                                    │
                                    ▼
                    ┌───────────────────────────────┐
                    │ SegmentTypeResolver Decision  │
                    ├───────────────────────────────┤
                    │ if (hasProductFilters) {      │
                    │   return 'dynamic_tag';       │
                    │ }                             │
                    └───────────────────────────────┘
```

---

## Multi-Product Selection Workflow

```
┌───────────────────────────────────────────────────────────────────┐
│          MULTI-PRODUCT CHECKBOX UI (OR Logic Example)            │
└───────────────────────────────────────────────────────────────────┘

SCENARIO: Target users subscribed to ANY of 3 products

Admin Action:                    UI State:                    Query Generated:
─────────────                    ─────────                    ────────────────

Select field:
"Subscribed to Product"
         │
         ▼
Select operator: ─────────────►  Dropdown shows:             <select>
"In"                             ☑ Equals                       <option>Equals
         │                       ☐ Not Equals                   <option>In ✓
         │                       ☑ In (selected)                <option>...
         │                       ☐ Not In                     </select>
         │                       └── Multi-product UI
         │                           appears
         ▼
Check products: ──────────────►  ┌─────────────────────┐    User::whereHas(
                                 │ Product Selection   │      'productSubs',
☑ Premium Newsletter (5)         ├─────────────────────┤      fn($q) => $q
☑ Webinar Access (8)             │ ☑ Premium News...   │        ->whereIn(
☐ VIP Events (12)                │ ☑ Webinar Access    │          'product_id',
                                 │ ☐ VIP Events        │          [5, 8]
         │                       │ ☐ Content Library   │        )
         │                       │ ☐ API Access        │        ->where(
         │                       └─────────────────────┘          'is_active',
         │                       wire:model.live fires            true
         ▼                       on each checkbox change        )
                                         │                    )
Live preview updates: ◄──────────────────┘
• Query executes
• Count: 2,345 users
• Sample users refresh
• Product badges shown:
  ├─ User 1: ✓ Premium Newsletter
  └─ User 2: ✓ Webinar Access, ✓ Premium Newsletter

┌───────────────────────────────────────────────────────────────────┐
│                    LOGIC COMPARISON TABLE                         │
├────────────┬───────────────────────────┬──────────────────────────┤
│  Operator  │  UI Behavior              │  Query Logic             │
├────────────┼───────────────────────────┼──────────────────────────┤
│  "Equals"  │  Single dropdown          │  WHERE product_id = X    │
│            │  Select 1 product         │  (exact match)           │
├────────────┼───────────────────────────┼──────────────────────────┤
│  "In"      │  Multi-checkbox list      │  WHERE product_id IN     │
│            │  Select multiple          │  (5, 8, 12) - OR logic   │
├────────────┼───────────────────────────┼──────────────────────────┤
│  Multiple  │  Add separate rules with  │  Multiple whereHas()     │
│  Rules     │  AND group logic          │  - AND logic             │
│  (AND)     │  (Rule 1 AND Rule 2)      │  (subscribed to ALL)     │
└────────────┴───────────────────────────┴──────────────────────────┘
```

---

## Opted-Out User Badge UI

```
┌───────────────────────────────────────────────────────────────────┐
│          OPTED-OUT USER BADGE (Win-Back Campaign UI)              │
└───────────────────────────────────────────────────────────────────┘

SCENARIO: Filter users who opted out of "Webinar Access"

Segment Builder Preview:
────────────────────────

┌─────────────────────────────────────────────────────────────────┐
│ Sample Users (First 5)                                          │
├─────────────────────────────────────────────────────────────────┤
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ John Doe (john@example.com)                     Pro Tier    │ │
│ │ ────────────────────────────────────────────────────────────│ │
│ │ ✓ Premium Newsletter                                        │ │
│ │ ✗ Webinar Access (opted out)   ← RED BADGE                 │ │
│ │   └─ Unsubscribed: 2025-10-15                              │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ Jane Smith (jane@example.com)                  Ent Tier     │ │
│ │ ────────────────────────────────────────────────────────────│ │
│ │ ✓ Premium Newsletter                                        │ │
│ │ ✗ Webinar Access (opted out)   ← RED BADGE                 │ │
│ │   └─ Unsubscribed: 2025-09-22                              │ │
│ │ ✓ VIP Events                                                │ │
│ └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

Blade Template:
───────────────

@foreach($user->productSubscriptions->where('is_active', false)
                                    ->whereNotNull('unsubscribed_at') as $sub)
    <span class="inline-flex items-center px-2 py-0.5 text-xs rounded
                 bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300">
        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10..."/>
        </svg>
        {{ $sub->product->name }} (opted out)
    </span>
    <span class="text-xs text-gray-500">
        Unsubscribed: {{ $sub->unsubscribed_at->format('Y-m-d') }}
    </span>
@endforeach
```

---

## Database Index Performance Impact

```
┌───────────────────────────────────────────────────────────────────┐
│        QUERY PERFORMANCE: WITH vs WITHOUT COMPOSITE INDEXES       │
└───────────────────────────────────────────────────────────────────┘

TEST SCENARIO: 60,000 users, 15,000 product subscriptions,
               Query for users subscribed to Product ID 5 OR 8

┌─────────────────────────────────────────────────────────────────┐
│                      WITHOUT INDEXES                            │
├─────────────────────────────────────────────────────────────────┤
│ Query:                                                          │
│   User::whereHas('productSubscriptions', fn($q) =>             │
│     $q->whereIn('product_id', [5, 8])->where('is_active', 1)   │
│   )->get()                                                      │
│                                                                 │
│ Execution Plan:                                                 │
│   ├── Full table scan on user_product_subscription             │
│   ├── Filter rows with product_id IN (5, 8)                    │
│   ├── Filter rows with is_active = 1                           │
│   └── JOIN with users table                                    │
│                                                                 │
│ Rows Scanned:  15,000 (full table)                             │
│ Rows Matched:  2,345                                            │
│ Execution Time: 5.2 seconds ⚠️ SLOW                             │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│           WITH COMPOSITE INDEX: idx_user_product_active         │
│           (user_id, product_id, is_active)                      │
├─────────────────────────────────────────────────────────────────┤
│ Query: (same as above)                                          │
│                                                                 │
│ Execution Plan:                                                 │
│   ├── Index seek on idx_user_product_active                    │
│   ├── Filtered by product_id IN (5, 8) AND is_active = 1       │
│   └── JOIN with users table (already indexed on id)            │
│                                                                 │
│ Rows Scanned:  2,450 (index seek)                              │
│ Rows Matched:  2,345                                            │
│ Execution Time: 0.4 seconds ✅ FAST (13x improvement)           │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                   REQUIRED INDEXES SUMMARY                      │
├─────────────────────────────────────────────────────────────────┤
│ 1. idx_user_active (user_id, is_active)                        │
│    → For single-user queries                                   │
│                                                                 │
│ 2. idx_product_active (product_id, is_active)                  │
│    → For product-centric queries                               │
│                                                                 │
│ 3. idx_user_product_active (user_id, product_id, is_active)    │
│    → For multi-product OR/AND queries ⚠️ CRITICAL               │
│                                                                 │
│ 4. idx_unsubscribed_at (unsubscribed_at)                       │
│    → For opted-out user queries (win-back campaigns)           │
│                                                                 │
│ 5. idx_subscribed_at (subscribed_at)                           │
│    → For timeline-based queries (new subscriber welcome)       │
└─────────────────────────────────────────────────────────────────┘

PRODUCTION REQUIREMENT: ALL 5 indexes MUST be created before launch
```

---

## Campaign Metrics Fetch Workflow

```
┌───────────────────────────────────────────────────────────────────┐
│         CAMPAIGN METRICS SYNCHRONIZATION (HYBRID APPROACH)        │
│         Webhook (Real-time) + Polling (Hourly) + Historical      │
└───────────────────────────────────────────────────────────────────┘

WEBHOOK FLOW (Real-time - as events occur)
─────────────────────────────────────────

CAMPAIGN MONITOR          WEBHOOK HANDLER                CDP DATABASE
────────────────          ───────────────                ────────────

Email Sent
  ├─ User opens email
  │       │
  │       └──► POST /webhooks/cm ─────────►  Verify signature
  │           {                                    │
  │             event: 'open',                     ▼
  │             campaign_id: 'ABC123',      HandleCampaignMetric
  │             email: 'user@...',          WebhookJob
  │             timestamp: '...'            Queue: webhooks
  │           }                             Delay: ~1-2s
  │                                                │
  │                                                ├──► Find campaign
  │                                                │    WHERE cm_campaign_id
  │                                                │    = 'ABC123'
  │                                                │
  │                                                ├──► Upsert to
  │                                                │    campaign_metrics
  │                                                │    table
  │                                                │    (date = today)
  │                                                │    INCREMENT opens
  │                                                │
  │                                                └──► Refresh Campaign
  │                                                     model aggregates
  │                                                     opens_count++
  │
  ├─ User clicks link
  │       │
  │       └──► POST /webhooks/cm ─────────►  (Same flow as above)
  │           { event: 'click', ... }        INCREMENT clicks
  │
  └─ User unsubscribes
          │
          └──► POST /webhooks/cm ─────────►  (Same flow as above)
              { event: 'unsubscribe', ... }  INCREMENT unsubscribes


POLLING FLOW (Hourly scheduled job for validation & gap-filling)
─────────────────────────────────────────────────────────────────

TIME            SCHEDULER                    CM API                   DATABASE
────            ─────────                    ──────                   ────────

Every Hour  ►  FetchCampaignMetricsJob
(0:00)         Queue: cm-sync
               Tries: 3
               Timeout: 180s
                       │
                       ├──► 1. Get campaigns sent in last 24h
                       │    Campaigns::where('sent_at', '>=', now()->subDay())
                       │              ->whereNotNull('cm_campaign_id')
                       │              ->get()
                       │    Result: 4 campaigns
                       │
                       ├──► 2. Fetch summary for each ──────────►  GET /campaigns/
                       │    (Batch process 10 at a time)             {cm_id}/summary
                       │                                             {
                       │                                               TotalOpens: 156
                       │                                               UniqueOpens: 98
                       │                                               Clicks: 42
                       │                                               UniqueClicks: 28
                       │                                               Bounces: 3
                       │                                               Unsubscribed: 1
                       │    ◄───────────────────────────────────────  SpamComplaints: 0
                       │                                             }
                       │
                       ├──► 3. Upsert to campaign_metrics ──────►  campaign_metrics
                       │    CampaignMetric::updateOrCreate(        table
                       │      ['campaign_id' => $id,                INSERT/UPDATE
                       │       'date' => today()],                  daily record
                       │      [
                       │        'opens' => 156,
                       │        'unique_opens' => 98,
                       │        'clicks' => 42,
                       │        'unique_clicks' => 28,
                       │        'bounces' => 3,
                       │        'unsubscribes' => 1,
                       │        'spam_reports' => 0,
                       │      ]
                       │    )
                       │
                       └──► 4. Refresh Campaign model ────────────►  campaigns table
                            $campaign->refreshMetrics()             UPDATE aggregates
                            • Sum all metrics records               opens_count
                            • Calculate rates                       clicks_count
                            • Update last_metrics_sync              last_metrics_sync


HISTORICAL IMPORT (One-time command for backfilling)
─────────────────────────────────────────────────────

ARTISAN CLI              COMMAND HANDLER               CM API           DATABASE
───────────              ───────────────               ──────           ────────

$ php artisan
  cdp:import-
  historical-
  campaign-
  metrics
      │
      │ --from=2024-01-01
      │ --to=2025-11-12
      │
      ▼
ImportHistorical
CampaignMetrics
Command
      │
      ├──► 1. Get all campaigns in date range
      │    Campaigns::whereBetween('sent_at', [$from, $to])
      │              ->whereNotNull('cm_campaign_id')
      │              ->get()
      │    Result: 482 campaigns
      │
      ├──► 2. Show confirmation
      │    │
      │    │  "Found 482 campaigns"
      │    │  "This will make ~482 API calls"
      │    │  "Estimated time: 16 minutes"
      │    │  "Continue? (yes/no)"
      │    │
      │    └──► User confirms: yes
      │
      ├──► 3. Process with progress bar
      │    ProgressBar::start(482)
      │         │
      │         │ Loop through campaigns (batch 10)
      │         │
      │         ├──► Fetch from CM API ────────────────►  GET /campaigns/
      │         │    (10 concurrent requests)               {cm_id}/summary
      │         │                                           (x10 parallel)
      │         │                                                │
      │         │    ◄─────────────────────────────────────────┘
      │         │    10 summary responses
      │         │
      │         ├──► Batch insert to DB ──────────────────►  campaign_metrics
      │         │    CampaignMetric::upsert([               table
      │         │      [...10 records...],                  BULK INSERT
      │         │      ['campaign_id', 'date'],             (faster)
      │         │      ['opens', 'clicks', ...]
      │         │    ])
      │         │
      │         └──► ProgressBar::advance(10)
      │              [=========>          ] 48/482
      │
      └──► 4. Final summary
           │
           │  ✓ Imported 482 campaigns
           │  ✓ Created 482 metric records
           │  ✓ API calls: 482
           │  ✓ Duration: 14m 32s
           │  ✓ Errors: 0


┌───────────────────────────────────────────────────────────────────┐
│                    DATABASE SCHEMA ADDITION                       │
├───────────────────────────────────────────────────────────────────┤
│  campaign_metrics table                                           │
│  ├─ id (PK)                                                       │
│  ├─ campaign_id (FK) → campaigns.id                              │
│  ├─ date (Date) - Metrics grouped by date                        │
│  ├─ opens (Integer)                                               │
│  ├─ unique_opens (Integer)                                        │
│  ├─ clicks (Integer)                                              │
│  ├─ unique_clicks (Integer)                                       │
│  ├─ bounces (Integer)                                             │
│  ├─ unsubscribes (Integer)                                        │
│  ├─ spam_reports (Integer)                                        │
│  ├─ created_at (Timestamp)                                        │
│  └─ updated_at (Timestamp)                                        │
│                                                                   │
│  Indexes:                                                         │
│  ├─ PRIMARY KEY (id)                                              │
│  ├─ INDEX idx_campaign_date (campaign_id, date) ← CRITICAL       │
│  └─ INDEX idx_date (date)                                         │
└───────────────────────────────────────────────────────────────────┘


┌───────────────────────────────────────────────────────────────────┐
│                  CAMPAIGNS TABLE ENHANCEMENTS                     │
├───────────────────────────────────────────────────────────────────┤
│  New fields added to campaigns table:                             │
│  ├─ opens_count (Integer) - Aggregate from campaign_metrics      │
│  ├─ unique_opens_count (Integer)                                  │
│  ├─ clicks_count (Integer)                                        │
│  ├─ unique_clicks_count (Integer)                                 │
│  ├─ bounce_count (Integer)                                        │
│  ├─ unsubscribe_count (Integer)                                   │
│  └─ last_metrics_sync (Timestamp)                                 │
│                                                                   │
│  Computed attributes (calculated on-demand):                      │
│  ├─ open_rate (%) = (unique_opens / recipient_count) * 100       │
│  ├─ click_rate (%) = (unique_clicks / recipient_count) * 100     │
│  └─ click_to_open_rate (%) = (unique_clicks / unique_opens)*100  │
└───────────────────────────────────────────────────────────────────┘


┌───────────────────────────────────────────────────────────────────┐
│                    API USAGE IMPACT ANALYSIS                      │
├───────────────────────────────────────────────────────────────────┤
│  Without metrics polling (current baseline):                     │
│  └─ ~478 calls/month                                              │
│                                                                   │
│  With hourly polling (24 campaigns/day average):                 │
│  ├─ Hourly job: 24 campaigns × 24 hours = 576 calls/day          │
│  ├─ Monthly: 576 × 30 = 17,280 calls/month                       │
│  └─ New total: 17,758 calls/month (+3,609% increase) ⚠️          │
│                                                                   │
│  OPTIMIZATION: Reduce polling to every 2-4 hours                  │
│  ├─ Every 2 hours: 24 campaigns × 12 polls = 288 calls/day       │
│  ├─ Monthly: 288 × 30 = 8,640 calls/month                        │
│  └─ New total: 9,118 calls/month (+1,806% increase)              │
│                                                                   │
│  Recommended approach:                                            │
│  ├─ Use webhooks as primary source (real-time, 0 API calls)      │
│  ├─ Poll every 4 hours for validation (216 calls/day)            │
│  └─ Monthly: 6,958 calls/month (+1,355% increase)                │
│                                                                   │
│  Historical import (one-time):                                   │
│  └─ ~500 campaigns = 500 API calls (one-time cost)               │
└───────────────────────────────────────────────────────────────────┘


UI INTEGRATION (CampaignDetail Component)
─────────────────────────────────────────

┌─────────────────────────────────────────────────────────────────┐
│  Campaign Detail View                                           │
│  /admin/cdp/campaigns/{id}                                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Campaign: "Weekly Newsletter - Nov 12, 2025"                  │
│  Status: Sent ✓    Sent: Nov 12, 2025 8:00 AM                 │
│  Recipients: 2,345                                              │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Performance Metrics          Last updated: 2 min ago    │  │
│  ├──────────────────────────────────────────────────────────┤  │
│  │                                                           │  │
│  │  Opens                      Clicks                       │  │
│  │  ─────────                  ──────────                   │  │
│  │  156 (98 unique)            42 (28 unique)               │  │
│  │  Open Rate: 4.2%            Click Rate: 1.2%             │  │
│  │                             Click-to-Open: 28.6%         │  │
│  │                                                           │  │
│  │  Bounces        Unsubscribes       Spam Reports          │  │
│  │  ────────       ────────────       ────────────          │  │
│  │  3 (0.13%)      1 (0.04%)          0 (0.00%)            │  │
│  │                                                           │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│  [View in Campaign Monitor] [Re-Sync Metrics]                  │
└─────────────────────────────────────────────────────────────────┘

wire:poll.30s (auto-refresh every 30 seconds for recent campaigns)
```

---

## Backfill Operations Architecture

```
┌───────────────────────────────────────────────────────────────────┐
│              BACKFILL OPERATIONS SYSTEM ARCHITECTURE              │
│              (Historical Data Synchronization & Validation)        │
└───────────────────────────────────────────────────────────────────┘

OPERATION #1: Backfill Activity Scores
───────────────────────────────────────

SCENARIO: System launched with activity tracking disabled.
          Need to calculate historical scores from existing data.

ARTISAN CLI          COMMAND HANDLER               DATABASE           SERVICES
───────────          ───────────────               ────────           ────────

$ php artisan
  cdp:backfill-
  activity-scores
      │
      │ --from=2024-01-01
      │ --to=2025-11-12
      │ --batch=1000
      │
      ▼
BackfillActivity
ScoresCommand
      │
      ├──► 1. Show analysis
      │    │
      │    │  Analyzing date range...
      │    │  ├─ Users: 60,000
      │    │  ├─ Date range: 2024-01-01 to 2025-11-12
      │    │  ├─ Batch size: 1,000
      │    │  ├─ Estimated batches: 60
      │    │  └─ Estimated time: 15-20 minutes
      │    │
      │    └──► Confirm? (yes/no): yes
      │
      ├──► 2. Process batches with progress bar
      │    │
      │    │  [=====>              ] 10/60 batches (16.7%)
      │    │
      │    └──► For each batch (1,000 users):
      │              │
      │              ├──► Query historical activity ──────────►  audit_logs
      │              │    SELECT user_id, action, created_at      table
      │              │    FROM audit_logs
      │              │    WHERE user_id IN (batch)
      │              │      AND created_at BETWEEN $from, $to
      │              │      AND action IN ('login', 'page_view',
      │              │                     'button_click', ...)
      │              │    Result: Array of activity records
      │              │
      │              ├──► Calculate scores ──────────────────►  ActivityScoring
      │              │    foreach user:                          Service
      │              │      activity_7d = countActions(          ::calculate()
      │              │        user, last 7 days
      │              │      )
      │              │      activity_30d = countActions(
      │              │        user, last 30 days
      │              │      )
      │              │
      │              └──► Batch update users ────────────────►  users table
      │                   User::upsert(                         UPDATE 1000
      │                     [...batch of 1000...],              rows
      │                     ['id'],
      │                     ['activity_score_7d',
      │                      'activity_score_30d',
      │                      'updated_at']
      │                   )
      │
      └──► 3. Show summary
           │
           │  ✓ Processed: 60,000 users
           │  ✓ Updated: 58,742 users (1,258 had no activity)
           │  ✓ Batches: 60
           │  ✓ Duration: 17m 43s
           │  ✓ Errors: 0
           │
           │  Score Distribution:
           │  ├─ High (>50): 8,234 users (14.0%)
           │  ├─ Medium (20-50): 22,156 users (37.7%)
           │  ├─ Low (1-19): 28,352 users (48.2%)
           │  └─ None (0): 1,258 users (2.1%)


OPERATION #2: Validate Event Attendances
─────────────────────────────────────────

SCENARIO: Data quality check - verify event attendance records
          are correctly linked and no orphaned records exist.

$ php artisan
  cdp:validate-
  event-
  attendances
      │
      │ --fix (optional, will auto-fix issues)
      │
      ▼
ValidateEvent
AttendancesCommand
      │
      ├──► 1. Scan for issues
      │    │
      │    │  Scanning event_attendances table...
      │    │
      │    ├──► Check #1: Orphaned user_id ──────────────────►  event_attendances
      │    │    SELECT * FROM event_attendances                 LEFT JOIN users
      │    │    WHERE user_id NOT IN (SELECT id FROM users)     Result: 3 orphaned
      │    │    Result: 3 records
      │    │
      │    ├──► Check #2: Orphaned event_id ─────────────────►  event_attendances
      │    │    SELECT * FROM event_attendances                 LEFT JOIN events
      │    │    WHERE event_id NOT IN (SELECT id FROM events)   Result: 0 orphaned
      │    │    Result: 0 records
      │    │
      │    ├──► Check #3: Duplicate attendances ──────────────►  event_attendances
      │    │    SELECT user_id, event_id, COUNT(*)              GROUP BY
      │    │    FROM event_attendances                          Result: 12 dupes
      │    │    GROUP BY user_id, event_id
      │    │    HAVING COUNT(*) > 1
      │    │    Result: 12 duplicates (24 records total)
      │    │
      │    └──► Check #4: Future attended_at dates ──────────►  event_attendances
      │         SELECT * FROM event_attendances                WHERE attended_at
      │         WHERE attended_at > NOW()                      > NOW()
      │         Result: 2 records                              Result: 2 records
      │
      ├──► 2. Show report
      │    │
      │    │  VALIDATION REPORT
      │    │  ═════════════════
      │    │
      │    │  ✗ Issue #1: Orphaned user_id (3 records)
      │    │    ├─ Record ID: 4567 → user_id: 999 (deleted)
      │    │    ├─ Record ID: 8901 → user_id: 1042 (deleted)
      │    │    └─ Record ID: 9012 → user_id: 1078 (deleted)
      │    │
      │    │  ✓ Issue #2: No orphaned event_id
      │    │
      │    │  ✗ Issue #3: Duplicate attendances (12 users)
      │    │    ├─ user_id: 123, event_id: 5 (2 records)
      │    │    ├─ user_id: 456, event_id: 8 (2 records)
      │    │    └─ ... (10 more)
      │    │
      │    │  ✗ Issue #4: Future attended_at (2 records)
      │    │    ├─ Record ID: 1234 → attended_at: 2026-01-01
      │    │    └─ Record ID: 5678 → attended_at: 2025-12-25
      │    │
      │    │  Total Issues: 17
      │    │
      │    └──► Prompt: Fix issues automatically? (yes/no)
      │
      └──► 3. Apply fixes (if --fix flag or user confirms)
           │
           ├──► Fix #1: Delete orphaned records ───────────────►  DELETE FROM
           │    DELETE FROM event_attendances                     event_attendances
           │    WHERE id IN (4567, 8901, 9012)                    Result: 3 deleted
           │
           ├──► Fix #2: Remove duplicate attendances ──────────►  Keep earliest,
           │    Keep earliest record, delete duplicates           delete rest
           │    DELETE FROM event_attendances                     Result: 12 deleted
           │    WHERE id IN (...)
           │
           ├──► Fix #3: Correct future dates ──────────────────►  UPDATE to event
           │    UPDATE event_attendances                          date
           │    SET attended_at = (SELECT event_date FROM events  Result: 2 updated
           │                       WHERE id = event_id)
           │    WHERE id IN (1234, 5678)
           │
           └──► Summary:
                │
                │  ✓ Deleted: 15 records
                │  ✓ Updated: 2 records
                │  ✓ Fixed: 17 issues
                │  ✓ Data quality restored


OPERATION #3: Backfill Product Subscriptions
─────────────────────────────────────────────

SCENARIO: Legacy system had implicit product subscriptions.
          Need to create explicit records in user_product_subscription table.

$ php artisan
  cdp:backfill-
  product-
  subscriptions
      │
      │ --source=legacy_products_csv
      │ --dry-run (optional, preview only)
      │
      ▼
BackfillProduct
SubscriptionsCommand
      │
      ├──► 1. Load source data
      │    │
      │    │  Reading CSV: legacy_products.csv
      │    │  ├─ Rows: 4,567
      │    │  ├─ Format: email, product_slug, subscribed_date
      │    │  └─ Validation: OK
      │    │
      │    └──► Parse into structured array
      │
      ├──► 2. Match to existing records
      │    │
      │    │  Matching users and products...
      │    │  [=========>          ] 2,567/4,567 (56.2%)
      │    │
      │    ├──► For each CSV row:
      │    │    │
      │    │    ├──► Find user by email ──────────────────────►  users table
      │    │    │    User::where('email', $row['email'])->first()
      │    │    │    Result: User model or null
      │    │    │
      │    │    ├──► Find product by slug ───────────────────►  products table
      │    │    │    Product::where('slug', $row['product_slug'])
      │    │    │             ->first()
      │    │    │    Result: Product model or null
      │    │    │
      │    │    └──► Check if subscription exists ───────────►  user_product_
      │    │         UserProductSubscription::where([              subscription
      │    │           'user_id' => $user->id,                    table
      │    │           'product_id' => $product->id
      │    │         ])->exists()
      │    │         Result: true/false
      │    │
      │    └──► Results:
      │         ├─ Valid matches: 4,234 (92.7%)
      │         ├─ User not found: 156 (3.4%)
      │         ├─ Product not found: 89 (1.9%)
      │         └─ Already exists: 88 (1.9%)
      │
      ├──► 3. Preview changes (if --dry-run)
      │    │
      │    │  DRY RUN MODE - No changes will be made
      │    │  ═══════════════════════════════════════
      │    │
      │    │  Would create 4,234 subscriptions:
      │    │  ├─ john@example.com → Premium Newsletter
      │    │  ├─ jane@example.com → Webinar Access
      │    │  └─ ... (4,232 more)
      │    │
      │    │  Skipped records (333):
      │    │  ├─ User not found: 156
      │    │  ├─ Product not found: 89
      │    │  └─ Already exists: 88
      │    │
      │    └──► Exit (no changes made)
      │
      └──► 4. Insert subscriptions (if not --dry-run)
           │
           │  Creating subscriptions...
           │  [===================] 4,234/4,234 (100%)
           │
           ├──► Batch insert (chunks of 500) ─────────────────►  user_product_
           │    UserProductSubscription::insert([                 subscription
           │      [...chunk of 500...],                           table
           │    ])                                                BULK INSERT
           │    Repeat 9 times (4,234 ÷ 500 = 9 batches)         4,234 rows
           │
           └──► Summary:
                │
                │  ✓ Created: 4,234 subscriptions
                │  ✓ Skipped: 333 records (see log)
                │  ✓ Duration: 3m 12s
                │  ✓ Errors: 0
                │
                │  Distribution by product:
                │  ├─ Premium Newsletter: 1,892 (44.7%)
                │  ├─ Webinar Access: 1,456 (34.4%)
                │  ├─ VIP Events: 623 (14.7%)
                │  └─ Other: 263 (6.2%)


┌───────────────────────────────────────────────────────────────────┐
│                   BACKFILL OPERATIONS SUMMARY                     │
├───────────────────────────────────────────────────────────────────┤
│                                                                   │
│  Command                        Purpose            Duration       │
│  ─────────────────────────────  ─────────────────  ─────────────  │
│                                                                   │
│  cdp:backfill-activity-scores   Recalculate scores 15-20 min     │
│                                 from historical                   │
│                                 audit logs                        │
│                                                                   │
│  cdp:validate-event-attendances Data quality check  2-5 min      │
│                                 for event records                 │
│                                                                   │
│  cdp:backfill-product-          Import legacy       3-10 min     │
│  subscriptions                  subscription data                │
│                                                                   │
│  All operations:                                                  │
│  ├─ Support --dry-run mode (preview only)                        │
│  ├─ Use progress bars for long operations                        │
│  ├─ Batch processing for performance                             │
│  ├─ Show detailed summaries                                      │
│  └─ Log all changes to audit_logs table                          │
│                                                                   │
└───────────────────────────────────────────────────────────────────┘
```

---

## Re-Sync Tool Workflow

```
┌───────────────────────────────────────────────────────────────────┐
│              RE-SYNC MANAGER - ADMIN TOOL WORKFLOW                │
│              (Manual Data Correction & Synchronization)           │
└───────────────────────────────────────────────────────────────────┘

ADMIN UI                    LIVEWIRE COMPONENT            QUEUE/JOBS
────────                    ──────────────────            ──────────

┌─────────────────────────────────────────────────────────────────┐
│  Re-Sync Manager                    /admin/cdp/re-sync          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌────────────────────────┐  ┌────────────────────────┐        │
│  │  Panel 1: Sync All     │  │  Panel 2: Sync Segment │        │
│  │                        │  │                        │        │
│  │  Total Users: 60,000   │  │  Select segment:       │        │
│  │                        │  │  [Dropdown ▼          ]│        │
│  │  [Sync All Users]      │  │                        │        │
│  │                        │  │  Users: 2,345          │        │
│  │  Progress: 0%          │  │                        │        │
│  │  [               ]     │  │  [Sync Segment]        │        │
│  └────────────────────────┘  └────────────────────────┘        │
│                                                                 │
│  ┌────────────────────────┐  ┌────────────────────────┐        │
│  │  Panel 3: Single User  │  │  Panel 4: Recalc Scores│        │
│  │                        │  │                        │        │
│  │  Email:                │  │  Recalculate activity  │        │
│  │  [________________]    │  │  scores for all users  │        │
│  │  [Search]              │  │                        │        │
│  │                        │  │  [Recalculate]         │        │
│  │  User: Not found       │  │                        │        │
│  │  [Sync User]           │  │  Progress: 0%          │        │
│  └────────────────────────┘  └────────────────────────┘        │
│                                                                 │
│  Recent Operations                                              │
│  ├─ 2025-11-12 14:30 - Sync All (60,000 users) - Completed     │
│  ├─ 2025-11-11 09:15 - Sync Segment "Pro Tier" - Completed     │
│  └─ 2025-11-10 16:42 - Recalc Scores - Completed               │
└─────────────────────────────────────────────────────────────────┘


WORKFLOW #1: Sync All Users to Campaign Monitor
────────────────────────────────────────────────

Step 1: Admin clicks "Sync All Users"
      │
      ├──► Trigger confirmation modal ──────────────────────►  wire:click=
      │                                                         "confirmSyncAll"
      │    ┌──────────────────────────────────────────────┐
      │    │  Confirm Full Sync                           │
      │    ├──────────────────────────────────────────────┤
      │    │                                              │
      │    │  This will sync ALL 60,000 users to         │
      │    │  Campaign Monitor.                           │
      │    │                                              │
      │    │  Estimated impact:                           │
      │    │  ├─ API calls: ~60 (batched 1,000 each)     │
      │    │  ├─ Duration: ~2 hours                       │
      │    │  └─ Cannot be cancelled once started         │
      │    │                                              │
      │    │  [Cancel]              [Confirm & Start]     │
      │    └──────────────────────────────────────────────┘
      │
      └──► Admin confirms


Step 2: Dispatch job
      │
      ├──► ReSyncManager.php ──────────────────────────────►  executeSyncAll()
      │    │                                                       │
      │    │                                                       ▼
      │    │                                                 ReSyncAllUsersJob
      │    │                                                 ::dispatch()
      │    │                                                 Queue: cm-sync
      │    │                                                 Tries: 1
      │    │                                                 Timeout: 2 hours
      │    │                                                       │
      │    │                                                       ▼
      │    │                                                 Job starts
      │    │                                                 processing
      │    │
      │    └──► Flash success message
      │         session()->flash('success', 'Full sync started')


Step 3: Job processes batches (background)
      │
      │  ReSyncAllUsersJob::handle()
      │       │
      │       ├──► Get all users (chunked)
      │       │    User::where('permission_to_track', true)
      │       │        ->chunk(1000, function($users) { ... })
      │       │
      │       ├──► For each batch (1,000 users):
      │       │    │
      │       │    ├──► Format for CM API ────────────────────►  Build subscriber
      │       │    │    $subscribers = $users->map(fn($u) => [   array
      │       │    │      'EmailAddress' => $u->email,
      │       │    │      'Name' => $u->fullname,
      │       │    │      'CustomFields' => [
      │       │    │        ['Key' => 'tier_name',
      │       │    │         'Value' => $u->tier_name],
      │       │    │        ['Key' => 'activity_score_7d',
      │       │    │         'Value' => $u->activity_score_7d],
      │       │    │        ...
      │       │    │      ],
      │       │    │      'ConsentToTrack' => 'Yes',
      │       │    │    ])
      │       │    │
      │       │    ├──► Bulk import to CM ──────────────────────►  CM API
      │       │    │    CampaignMonitorService                     POST /
      │       │    │      ::bulkImportSubscribers($subscribers)    subscribers
      │       │    │    API Call: 1 request for 1,000 users        /import
      │       │    │                                                {
      │       │    │                                                  Subscribers:
      │       │    │                                                  [...]
      │       │    │                                                }
      │       │    │                                                      │
      │       │    │    ◄───────────────────────────────────────────────┘
      │       │    │    Response: 200 OK
      │       │    │    { Imported: 998, Duplicates: 2 }
      │       │    │
      │       │    ├──► Update progress ────────────────────────►  Cache::put(
      │       │    │    Cache::put(                                  'resync_all
      │       │    │      'resync_all_progress',                     _progress',
      │       │    │      [                                          [...data...]
      │       │    │        'processed' => 1000,                   )
      │       │    │        'total' => 60000,
      │       │    │        'percentage' => 1.67,
      │       │    │      ],
      │       │    │      now()->addHours(2)
      │       │    │    )
      │       │    │
      │       │    └──► Update users table ───────────────────────►  UPDATE users
      │       │         User::whereIn('id', $userIds)                SET
      │       │              ->update([                               cm_synced_at
      │       │                'cm_synced_at' => now(),              = NOW()
      │       │                'cm_status' => 'active'
      │       │              ])
      │       │
      │       └──► Repeat for all 60 batches (60,000 ÷ 1,000)


Step 4: Real-time progress tracking (Livewire polling)
      │
      │  Re-Sync Manager UI (wire:poll.2s)
      │       │
      │       ├──► Poll every 2 seconds ───────────────────────►  Cache::get(
      │       │    public function getProgress()                   'resync_all
      │       │    {                                                _progress'
      │       │      return Cache::get('resync_all_progress');    )
      │       │    }                                                    │
      │       │                                                         ▼
      │       │                                                   Return:
      │       │                                                   {
      │       │                                                     processed:
      │       │                                                     15,000,
      │       │                                                     total: 60,000,
      │       │                                                     percentage:
      │       │                                                     25.0
      │       │                                                   }
      │       │
      │       └──► Update UI in real-time
      │            ┌────────────────────────┐
      │            │  Sync All Users        │
      │            │  Progress: 25%         │
      │            │  [=====          ]     │
      │            │  Processed: 15,000 /   │
      │            │            60,000      │
      │            │  Estimated: 1h 30m     │
      │            │  remaining             │
      │            └────────────────────────┘


Step 5: Job completes
      │
      ├──► Save operation log ───────────────────────────────►  resync_operations
      │    ResyncOperation::create([                            table
      │      'operation_type' => 'sync_all',                    INSERT record
      │      'started_at' => $startTime,
      │      'completed_at' => now(),
      │      'users_count' => 60000,
      │      'status' => 'completed',
      │      'metadata' => [
      │        'api_calls' => 60,
      │        'duration_seconds' => 7234,
      │        'errors' => 0,
      │      ]
      │    ])
      │
      ├──► Clear cache ──────────────────────────────────────►  Cache::forget(
      │    Cache::forget('resync_all_progress')                 'resync_all
      │                                                          _progress'
      │                                                        )
      │
      └──► Notification
           │
           │  ✓ Full sync completed!
           │  ├─ 60,000 users synced
           │  ├─ Duration: 2h 0m 34s
           │  └─ Errors: 0


WORKFLOW #2: Sync Segment
──────────────────────────

(Similar flow to Sync All, but only processes users matching segment query)

Step 1: Admin selects segment from dropdown
      │
      └──► Load segment details ──────────────────────────────►  Segment::find()
           Show user count (e.g., 2,345)                         ->execute()
                                                                 ->count()

Step 2: Admin clicks "Sync Segment"
      │
      └──► Confirmation modal (similar to Sync All)

Step 3: Dispatch job
      │
      └──► ReSyncSegmentJob::dispatch($segmentId)
           Queue: cm-sync


WORKFLOW #3: Sync Single User
──────────────────────────────

Step 1: Admin enters email and clicks "Search"
      │
      ├──► wire:model.live="userEmail" ─────────────────────►  User::where(
      │                                                          'email',
      │    ┌────────────────────────┐                           $userEmail
      │    │  User Found:           │                         )->first()
      │    │  John Doe              │                              │
      │    │  john@example.com      │                              ▼
      │    │  Pro Tier              │                         User model
      │    │  Last synced: 2 days   │◄──────────────────────  returned
      │    │  ago                   │
      │    │  [Sync User]           │
      │    └────────────────────────┘
      │
      └──► Admin clicks "Sync User"


Step 2: Sync immediately (no queue, instant)
      │
      └──► ReSyncManager.php ──────────────────────────────────►  syncSingleUser()
           │                                                           │
           │                                                           ▼
           │                                                     CampaignMonitor
           │                                                     Service
           │                                                     ::updateSubscriber
           │                                                     ($user)
           │                                                           │
           │                                                           ├──► API Call
           │                                                           │    PUT /
           │                                                           │    subscribers
           │                                                           │    /{email}
           │                                                           │
           │                                                           └──► Update
           │                                                                users table
           │
           └──► Flash success message
                "User synced successfully!"


WORKFLOW #4: Recalculate Scores
────────────────────────────────

(Same pattern as Sync All, but recalculates activity_score_7d and
activity_score_30d from audit_logs without calling CM API)


┌───────────────────────────────────────────────────────────────────┐
│                RE-SYNC MANAGER FEATURES SUMMARY                   │
├───────────────────────────────────────────────────────────────────┤
│                                                                   │
│  1. Sync All Users                                                │
│     ├─ Sync all 60,000 users to Campaign Monitor                 │
│     ├─ Batched processing (1,000 per batch)                      │
│     ├─ Real-time progress tracking                               │
│     └─ Duration: ~2 hours                                        │
│                                                                   │
│  2. Sync Segment                                                  │
│     ├─ Select specific segment                                   │
│     ├─ Only sync matching users                                  │
│     └─ Duration: Varies by segment size                          │
│                                                                   │
│  3. Sync Single User                                              │
│     ├─ Search by email                                           │
│     ├─ Instant sync (no queue)                                   │
│     └─ Duration: ~2 seconds                                      │
│                                                                   │
│  4. Recalculate Scores                                            │
│     ├─ Recalculate activity_score_7d and activity_score_30d      │
│     ├─ Uses audit_logs table                                     │
│     ├─ No CM API calls                                           │
│     └─ Duration: ~15-20 minutes                                  │
│                                                                   │
│  All operations:                                                  │
│  ├─ Confirmation modals with impact estimates                    │
│  ├─ Real-time progress bars (wire:poll.2s)                       │
│  ├─ Operation logging (resync_operations table)                  │
│  ├─ Recent operations history display                            │
│  └─ Dark mode support throughout                                 │
│                                                                   │
└───────────────────────────────────────────────────────────────────┘
```

---

**Document Status:** Historical Reference - Updated with Campaign Metrics, Backfill & Re-Sync
**Last Updated:** 2025-11-12
**Version:** 3.0 (Campaign Metrics & Backfill Enhancement)
