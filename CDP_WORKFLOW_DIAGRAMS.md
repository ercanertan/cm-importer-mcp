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

**Document Status:** Historical Reference - Updated with Product Filtering Workflows
**Last Updated:** 2025-11-12
**Version:** 2.1 (Product Filtering Enhancement)
