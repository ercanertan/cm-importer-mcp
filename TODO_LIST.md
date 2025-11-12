# CDP Project - Complete TODO List (UI-First Approach)

**Last Updated:** 2025-11-12
**Total Tasks:** 78 (reorganized with UI-first approach)
**Status:** Ready to Begin
**Tech Stack:** Laravel 12 + Livewire 3.6 + Flux Pro + Tailwind v4

This is the complete implementation checklist for the B2B Multi-Org CDP with Campaign Monitor integration, reorganized to prioritize UI/UX visualization for every CDP action.

---

## 📋 QUICK START GUIDE

### UI-First Philosophy
**Every backend action MUST have a corresponding UI visualization:**
- Data changes → Real-time dashboard updates
- Background jobs → Progress indicators with `wire:poll`
- Sync operations → Status cards with live updates
- Campaign sends → Preview → Confirm → Track workflow
- Segments → Visual query builder → Live preview

### Component Architecture
- **Livewire 3.6** (class-based components for admin, Volt for simple pages)
- **Flux Pro** (forms, buttons, inputs, navigation)
- **Custom Modals** (fixed z-50 pattern, not Flux modals)
- **Dark Mode** (all interfaces support `dark:` variants)
- **Wire Navigate** (SPA-like experience for all internal links)

---

## 🎯 Phase 1: Foundation UI & Models (Weeks 1-2) - 14 tasks

### Database & Models (Backend)
- [ ] **1.1** Design database schema for tier/subscription system
  - Tables: `tiers`, `products`, `product_tier`, `user_product_subscription`
  - Add to users: `activity_score_7d`, `activity_score_30d`, `last_login_at`
  - Migration files in `database/migrations/`

- [ ] **1.2** Create Tier model with relationships
  - File: `app/Models/Tier.php`
  - Relationships: `organizations()`, `products()`, `features()`
  - Scopes: `active()`, `byLevel()`

- [ ] **1.3** Create Product model with tier/user relationships
  - File: `app/Models/Product.php`
  - Relationships: `tiers()`, `subscribers()`, `subscriptions()`
  - Methods: `isAvailableForTier()`, `activeSubscribers()`

- [ ] **1.4** Create UserProductSubscription pivot model
  - File: `app/Models/UserProductSubscription.php`
  - Fields: `user_id`, `product_id`, `is_active`, `subscribed_at`, `unsubscribed_at`
  - Scopes: `active()`, `forProduct()`, `forUser()`

### Tier Management UI (Livewire + Flux)
- [ ] **1.5** Create TierManager Livewire component
  - File: `app/Livewire/Admin/Cdp/TierManager.php`
  - View: `resources/views/livewire/admin/cdp/tier-manager.blade.php`
  - Features: CRUD for tiers, assign features, set pricing
  - Flux components: `<flux:input>`, `<flux:button>`, `<flux:checkbox>`
  - Pattern: Follow existing `DomainManager` structure

- [ ] **1.6** Build tier list view with stats cards
  - View: Embedded in tier-manager.blade.php
  - Display: Tier name, organization count, user count, revenue (if applicable)
  - Real-time updates: `wire:poll.5s` for live stats
  - Dark mode: Full support with Tailwind dark: classes

- [ ] **1.7** Create tier creation/edit modal
  - Modal pattern: Custom Tailwind modal (not Flux modal)
  - Form fields: Name, slug, description, max_users, features (JSON), is_active
  - Validation: Real-time with `wire:model.live.debounce.500ms`
  - Feedback: Flash messages on success/error

### Product Management UI (Livewire + Flux)
- [ ] **1.8** Create ProductManager Livewire component
  - File: `app/Livewire/Admin/Cdp/ProductManager.php`
  - View: `resources/views/livewire/admin/cdp/product-manager.blade.php`
  - Features: CRUD for products, assign to tiers, manage descriptions
  - Search: `wire:model.live` for instant filtering

- [ ] **1.9** Build product list with tier availability matrix
  - Table view: Product name | Description | Available Tiers | Subscribers
  - Tier badges: Color-coded (Free: gray, Pro: blue, Enterprise: purple)
  - Subscriber count: Live count with `wire:poll`

- [ ] **1.10** Create product creation/edit modal
  - Form fields: Name, slug, description, tier_ids (multi-select), is_active
  - Tier assignment: Checkbox group with Flux checkboxes
  - Preview: Show which users will gain/lose access on tier change

### User Product Subscription UI (User-Facing)
- [ ] **1.11** Create ProductSubscriptionManager Livewire component (user-facing)
  - File: `app/Livewire/UserProfile/ProductSubscriptionManager.php`
  - View: `resources/views/livewire/user-profile/product-subscription-manager.blade.php`
  - Route: `/settings/products`
  - Features: View available products, opt-in/opt-out, see subscription history

- [ ] **1.12** Build product opt-in/opt-out interface
  - Card layout: Product cards with name, description, opt-in toggle
  - Toggle: Flux checkbox with `wire:model.live` → immediate save
  - Access control: Show only products available to user's tier
  - Feedback: Toast notifications on successful opt-in/opt-out

### Sidebar Navigation Updates
- [ ] **1.13** Add CDP menu group to sidebar
  - File: `resources/views/components/layouts/app/sidebar.blade.php`
  - Add new `<flux:navlist.group :heading="CDP Data">`
  - Menu items: Tiers, Products, Segments, Campaigns, Sync Status
  - Icons: Use existing Flux icons or add custom ones in `resources/views/flux/icon/`

- [ ] **1.14** Update user settings sidebar for product subscriptions
  - File: Settings layout sidebar
  - Add "Product Subscriptions" menu item
  - Route to ProductSubscriptionManager component

---

## 📊 Phase 2: Activity Tracking UI & Backend (Weeks 3-4) - 10 tasks

### Activity Scoring Backend
- [ ] **2.1** Migrate existing events table to Laravel models
  - Create Event model: `app/Models/Event.php`
  - Create EventAttendance model: `app/Models/EventAttendance.php`
  - Update relationships in User model: `events()`, `eventAttendances()`

- [ ] **2.2** Build user activity scoring system
  - Service: `app/Services/ActivityScoringService.php`
  - Methods: `calculateScore7d()`, `calculateScore30d()`, `updateUserScores()`
  - Logic: Weighted scoring (logins, events, page views, product usage)
  - Store in users table: `activity_score_7d`, `activity_score_30d`

- [ ] **2.3** Create daily activity score update job
  - File: `app/Jobs/UpdateActivityScoresJob.php`
  - Queue: `cdp-analytics`
  - Schedule: Daily at 2 AM (app/Console/Kernel.php)
  - Delta sync: Only update users with recent activity

### Activity Dashboard UI (Admin)
- [ ] **2.4** Create ActivityDashboard Livewire component
  - File: `app/Livewire/Admin/Cdp/ActivityDashboard.php`
  - View: `resources/views/livewire/admin/cdp/activity-dashboard.blade.php`
  - Route: `/admin/cdp/activity`
  - Features: User activity heatmap, top active users, engagement trends

- [ ] **2.5** Build activity stats cards
  - Cards: Total active users (7d), Total active users (30d), Avg score, Trend
  - Real-time: `wire:poll.30s` for live updates
  - Charts: Use Chart.js or similar (add via CDN or npm)
  - Dark mode: Chart theming with dark background

- [ ] **2.6** Create user activity timeline view
  - Component: Embedded in ActivityDashboard
  - Display: User name, last login, events attended, scores (7d/30d)
  - Filters: Date range, min score, tier filter
  - Search: Real-time search by name/email

### Activity Tracking UI (User-Facing)
- [ ] **2.7** Create MyActivityDashboard Livewire component (user-facing)
  - File: `app/Livewire/UserProfile/MyActivityDashboard.php`
  - View: `resources/views/livewire/user-profile/my-activity-dashboard.blade.php`
  - Route: `/dashboard/my-activity`
  - Features: Personal activity score, recent events, engagement tips

- [ ] **2.8** Build personal activity score card
  - Display: Current scores (7d/30d), historical trend, tier comparison
  - Gamification: Progress bar, achievement badges, leaderboard position
  - Tips: Suggestions to increase engagement score

### Event Tracking UI
- [ ] **2.9** Create EventManager Livewire component (admin)
  - File: `app/Livewire/Admin/Cdp/EventManager.php`
  - View: `resources/views/livewire/admin/cdp/event-manager.blade.php`
  - Features: Create events, track attendees, export attendance data
  - Attendance import: CSV upload for bulk attendance registration

- [ ] **2.10** Build event attendance tracking interface
  - Table: Event name, date, total attendees, avg activity boost
  - Attendance modal: View/edit attendees for specific event
  - Bulk actions: Mark attendance, send follow-up emails

---

## 🎯 Phase 3: Segmentation Engine UI & Backend (Weeks 5-6) - 12 tasks

### Segmentation Backend
- [ ] **3.1** Create Segment model and migration
  - File: `app/Models/Segment.php`
  - Table: `segments` (id, name, description, rules JSON, type, is_active, cm_segment_id)
  - Relationships: `campaigns()`, `users()` (dynamic via query)
  - Methods: `execute()`, `estimateCount()`, `preview()`

- [ ] **3.2** Build SegmentQueryBuilder service
  - File: `app/Services/SegmentQueryBuilder.php`
  - Methods: `build($rules)`, `addTierFilter()`, `addProductFilter()`, `addActivityFilter()`, `addEventFilter()`
  - Support: Complex AND/OR logic, nested conditions
  - Output: Eloquent query builder instance

- [ ] **3.3** Create segment type decision logic
  - Service: `app/Services/SegmentTypeResolver.php`
  - Method: `shouldUseDynamicTag($rules)` → bool
  - Logic: If rules involve events/complex JOINs → dynamic tag, else → persistent segment
  - Used by campaign routing

### Segment Builder UI (Visual Query Builder)
- [ ] **3.4** Create SegmentBuilder Livewire component
  - File: `app/Livewire/Admin/Cdp/SegmentBuilder.php`
  - View: `resources/views/livewire/admin/cdp/segment-builder.blade.php`
  - Route: `/admin/cdp/segments`
  - Features: Visual query builder, live preview, save segments

- [ ] **3.5** Build visual rule builder interface
  - UI: Add rule groups (AND/OR logic), drag-drop conditions
  - Rule types: Tier, Product, Activity Score, Event Attendance, Last Login, Custom Fields
  - Operators: Equals, Not Equals, Greater Than, Less Than, In, Not In
  - Flux components: `<flux:input>` for values, `<flux:select>` for operators

- [ ] **3.6** Add live segment preview with user count
  - Preview panel: Right sidebar showing matching user count
  - Real-time: `wire:model.live.debounce.1s` on rule changes → recalculate
  - Sample users: Show first 10 matching users (name, email, tier, scores)
  - Estimate: "~2,345 users match these criteria" with refresh button

- [ ] **3.7** Create segment save/edit modal
  - Form fields: Segment name, description, rules (auto-filled from builder)
  - Type badge: Show "Persistent Segment" or "Dynamic Tag" based on rules
  - Validation: Require at least one rule, unique name
  - Save action: Create segment record, optionally create CM segment

### Segment List UI
- [ ] **3.8** Create SegmentList Livewire component
  - File: `app/Livewire/Admin/Cdp/SegmentList.php`
  - View: `resources/views/livewire/admin/cdp/segment-list.blade.php`
  - Route: `/admin/cdp/segments/list`
  - Features: List all segments, edit, delete, view users, send campaign

- [ ] **3.9** Build segment table with stats
  - Columns: Name, Type, User Count, Last Updated, Campaigns Sent, Actions
  - User count: `wire:poll.60s` for live updates
  - Type badge: Color-coded (Persistent: green, Dynamic: orange)
  - Actions: Edit, Duplicate, Delete, Send Campaign

- [ ] **3.10** Add segment user list modal
  - Triggered by: Click user count in segment table
  - Display: Paginated list of users matching segment
  - Features: Export to CSV, send campaign to this segment
  - Real-time: Recalculate on modal open

### Segment Management Features
- [ ] **3.11** Create segment duplication feature
  - Action: "Duplicate" button in segment list
  - Workflow: Clone rules, prompt for new name, save as new segment
  - Use case: Quickly create variations of existing segments

- [ ] **3.12** Build segment comparison tool
  - Component: Compare 2-3 segments side-by-side
  - Display: Venn diagram of user overlap, unique counts per segment
  - Use case: Avoid duplicate campaign sends, optimize targeting

---

## 🔄 Phase 4: Campaign Monitor API Integration (Weeks 7-9) - 18 tasks

### Core API Setup
- [ ] **4.1** Install Campaign Monitor API client
  - Package: `composer require campaignmonitor/createsend-php`
  - Config: Add to `config/campaign-monitor.php` (API key, list ID, client ID)
  - Env vars: `CM_API_KEY`, `CM_LIST_ID`, `CM_CLIENT_ID`, `CM_WEBHOOK_SECRET`

- [ ] **4.2** Create CampaignMonitorService wrapper
  - File: `app/Services/CampaignMonitorService.php`
  - Methods: `addSubscriber()`, `updateSubscriber()`, `createSegment()`, `sendCampaign()`
  - Error handling: Wrap all API calls with try-catch, log failures
  - Rate limiting: Built-in throttling to respect CM API limits

- [ ] **4.3** Design HYBRID field mapping strategy
  - Document: Update `CDP_WORKFLOWS.md` with field map
  - Persistent fields (10): `cm_subscriber_id`, `tier_name`, `tier_id`, `primary_org_id`, `primary_org_name`, `active_products_count`, `activity_score_7d`, `activity_score_30d`, `last_login_date`, `account_status`
  - Dynamic fields (2): `temp_campaign_tag`, `temp_campaign_tag_2` (backup)
  - Create CM custom fields via API on first run

### Persistent Field Sync Backend
- [ ] **4.4** Build PersistentFieldSyncService
  - File: `app/Services/PersistentFieldSyncService.php`
  - Methods: `syncUser($user)`, `syncBatch($users)`, `getDeltaUsers()`
  - Delta sync: Only sync users with changes since last sync
  - Batch size: 1000 users per API call

- [ ] **4.5** Create SyncPersistentFieldsJob (daily)
  - File: `app/Jobs/SyncPersistentFieldsJob.php`
  - Queue: `cm-sync`
  - Schedule: Daily at 3 AM
  - Logic: Get delta users → batch sync → update `cm_synced_at`

- [ ] **4.6** Create SyncTierChangeJob (on-demand)
  - File: `app/Jobs/SyncTierChangeJob.php`
  - Queue: `cm-sync` (high priority)
  - Trigger: When organization tier changes
  - Logic: Sync all users in org → update tier_name/tier_id in CM

### Registration Sync Backend
- [ ] **4.7** Create UserWasRegistered event
  - File: `app/Events/UserWasRegistered.php`
  - Payload: User model
  - Dispatch: In RegisterController after user creation

- [ ] **4.8** Create SyncUserToCampaignMonitor listener
  - File: `app/Listeners/SyncUserToCampaignMonitor.php`
  - Listens to: UserWasRegistered event
  - Action: Dispatch AddSubscriberToCmJob

- [ ] **4.9** Build AddSubscriberToCmJob with duplicate handling
  - File: `app/Jobs/AddSubscriberToCmJob.php`
  - Queue: `cm-sync`
  - Logic: Check if exists → update if yes, add if no
  - Retry: 3 attempts with exponential backoff
  - GDPR: Set `ConsentToTrack` based on user permission

### Dynamic Tag System Backend
- [ ] **4.10** Create DynamicTagService
  - File: `app/Services/DynamicTagService.php`
  - Methods: `generateTag()`, `tagUsers($users, $tag)`, `clearTag($users)`, `cleanupExpiredTags()`
  - Tag format: `seg_YYYYMMDD_HHMMSS_slugified-name`
  - Expiry: Auto-cleanup tags older than 7 days

- [ ] **4.11** Build TagAndSendCampaignJob
  - File: `app/Jobs/TagAndSendCampaignJob.php`
  - Queue: `cm-campaigns`
  - Steps: Generate tag → tag users in CM → create CM segment → create campaign → send → track
  - Timeout: 5 minutes for large segments
  - Logging: Comprehensive logs at each step

- [ ] **4.12** Create CleanupCampaignTagJob
  - File: `app/Jobs/CleanupCampaignTagJob.php`
  - Queue: `cm-cleanup` (low priority)
  - Delay: 2 hours after campaign send
  - Logic: Clear temp_campaign_tag → delete CM segment → mark campaign as cleaned

### Webhook Handler Backend
- [ ] **4.13** Create CM webhook receiver endpoint
  - Route: `POST /webhooks/campaign-monitor`
  - Controller: `app/Http/Controllers/Webhooks/CampaignMonitorWebhookController.php`
  - Verification: Validate webhook signature with `CM_WEBHOOK_SECRET`
  - Events: Subscribe, Unsubscribe, Bounce, Open, Click

- [ ] **4.14** Build webhook event handlers
  - File: `app/Services/CampaignMonitorWebhookHandler.php`
  - Methods: `handleSubscribe()`, `handleUnsubscribe()`, `handleBounce()`, `handleOpen()`, `handleClick()`
  - Update user: Set `cm_status`, `cm_unsubscribed_at`, track email activity
  - Log: Store all webhook events in `cm_webhook_logs` table

### Sync Status UI (Real-Time Monitoring)
- [ ] **4.15** Create SyncStatusDashboard Livewire component
  - File: `app/Livewire/Admin/Cdp/SyncStatusDashboard.php`
  - View: `resources/views/livewire/admin/cdp/sync-status-dashboard.blade.php`
  - Route: `/admin/cdp/sync-status`
  - Features: Live sync progress, error logs, queue health, API usage stats

- [ ] **4.16** Build sync progress indicators
  - Cards: Last sync time, users synced, users pending, failed syncs
  - Progress bars: Real-time job progress with `wire:poll.2s`
  - Queue status: Jobs in queue (cm-sync, cm-campaigns, cm-cleanup)
  - API usage: Calls today, calls this month, estimated remaining quota

- [ ] **4.17** Create sync error log viewer
  - Table: Timestamp, user email, error message, retry count, status
  - Filters: Date range, error type, queue name
  - Actions: Retry failed job, mark as resolved, view full error details
  - Modal: View full error stack trace and API response

- [ ] **4.18** Add manual sync trigger interface
  - Button: "Sync All Users Now" (admin only, with confirmation)
  - Progress: Show real-time progress with job count
  - Estimate: "~60,000 users will be synced, estimated time: 15 minutes"
  - Safety: Require confirmation, prevent concurrent syncs

---

## 📧 Phase 5: Campaign Management UI & Backend (Weeks 10-11) - 16 tasks

### Recurring Campaign Backend
- [ ] **5.1** Create CampaignTemplate model and migration
  - File: `app/Models/CampaignTemplate.php`
  - Table: `campaign_templates` (id, name, cm_template_id, subject, type, schedule_config JSON)
  - Types: daily_newsletter, weekly_digest, monthly_report
  - Schedule config: Frequency, time, timezone, target tiers

- [ ] **5.2** Create Campaign model and migration
  - File: `app/Models/Campaign.php`
  - Table: `campaigns` (id, name, type, segment_id, cm_campaign_id, cm_segment_id, campaign_tag, recipient_count, status, sent_at, cleanup_completed_at, metadata JSON)
  - Relationships: `segment()`, `template()`, `recipients()`
  - Scopes: `sent()`, `pending()`, `failed()`

- [ ] **5.3** Build SendRecurringCampaignJob
  - File: `app/Jobs/SendRecurringCampaignJob.php`
  - Queue: `cm-campaigns`
  - Logic: Get segment → create CM campaign → send → track
  - Schedule: Configured in app/Console/Kernel.php (daily, weekly, monthly)

- [ ] **5.4** Configure Laravel Scheduler for recurring campaigns
  - File: `app/Console/Kernel.php` (create if doesn't exist)
  - Schedules: Daily newsletter (8 AM), Weekly digest (Fri 9 AM), Monthly report (1st of month 10 AM)
  - Timezone: Configure in config/app.php
  - Monitoring: Log all scheduled campaign dispatches

### Campaign Template UI
- [ ] **5.5** Create CampaignTemplateManager Livewire component
  - File: `app/Livewire/Admin/Cdp/CampaignTemplateManager.php`
  - View: `resources/views/livewire/admin/cdp/campaign-template-manager.blade.php`
  - Route: `/admin/cdp/campaign-templates`
  - Features: CRUD templates, configure schedules, test sends

- [ ] **5.6** Build template list with schedule info
  - Table: Template name, type, schedule, last sent, next send, status
  - Next send: Calculated from schedule config with countdown
  - Actions: Edit, Pause/Resume, Send Now, Delete
  - Status badges: Active (green), Paused (yellow), Draft (gray)

- [ ] **5.7** Create template creation/edit modal
  - Form: Name, type (select), CM template ID (link to CM), subject line, schedule config
  - Schedule config: Frequency (daily/weekly/monthly), time picker, day of week/month, timezone
  - Target tiers: Multi-select checkboxes (Free, Pro, Enterprise)
  - Preview: Show next 5 scheduled send times

### Complex Campaign UI (Main Campaign Builder)
- [ ] **5.8** Create CampaignBuilder Livewire component
  - File: `app/Livewire/Admin/Cdp/CampaignBuilder.php`
  - View: `resources/views/livewire/admin/cdp/campaign-builder.blade.php`
  - Route: `/admin/cdp/campaigns/create`
  - Features: Multi-step wizard (Select Segment → Configure Campaign → Preview → Send → Track)

- [ ] **5.9** Build Step 1: Segment Selection
  - UI: Dropdown to select existing segment OR button to create new segment
  - Create new: Opens SegmentBuilder in modal, saves, auto-selects
  - Preview: Show segment name, type, user count, sample users
  - Next button: Disabled until segment selected

- [ ] **5.10** Build Step 2: Campaign Configuration
  - Form: Campaign name, subject line, CM template (select), send time (now/scheduled)
  - Template preview: Show CM template thumbnail (if available)
  - Personalization: Support merge tags (name, tier, org, etc.)
  - Validation: Required fields, subject line max length

- [ ] **5.11** Build Step 3: Campaign Preview
  - Preview panel: Shows estimated recipient count, send time, cost estimate
  - Recipient list: Table of users who will receive (paginated, first 50)
  - Type indicator: Shows "Persistent Segment" or "Dynamic Tag" with explanation
  - Warning: If dynamic tag, show cleanup schedule (2 hours after send)

- [ ] **5.12** Build Step 4: Send & Track
  - Send button: Large, prominent, requires final confirmation
  - Confirmation modal: "Send to 2,345 users now? This cannot be undone."
  - Progress: Real-time job progress (Tagging → Creating Segment → Sending)
  - Success: Redirect to campaign detail page with tracking

### Campaign List & Tracking UI
- [ ] **5.13** Create CampaignList Livewire component
  - File: `app/Livewire/Admin/Cdp/CampaignList.php`
  - View: `resources/views/livewire/admin/cdp/campaign-list.blade.php`
  - Route: `/admin/cdp/campaigns`
  - Features: List all campaigns, filter, search, view details

- [ ] **5.14** Build campaign table with stats
  - Columns: Name, Type, Sent Date, Recipients, Opens, Clicks, Status
  - Stats: Opens and clicks from CM webhooks/API polling
  - Status: Sent (green), Sending (blue), Failed (red), Scheduled (yellow)
  - Actions: View Details, View Recipients, Clone, Cancel (if scheduled)

- [ ] **5.15** Create CampaignDetail Livewire component
  - File: `app/Livewire/Admin/Cdp/CampaignDetail.php`
  - View: `resources/views/livewire/admin/cdp/campaign-detail.blade.php`
  - Route: `/admin/cdp/campaigns/{id}`
  - Features: Full campaign analytics, recipient list, resend options

- [ ] **5.16** Build campaign analytics dashboard
  - Cards: Total sent, Opens (%), Clicks (%), Bounces, Unsubscribes
  - Charts: Opens over time, click heatmap, top clicked links
  - Timeline: Campaign created → Tagged → Sent → Cleanup completed
  - Export: Download recipient list with engagement data (CSV)

---

## 🚀 Phase 6: Optimization, Testing & Launch (Week 12) - 18 tasks

### Performance Optimization
- [ ] **6.1** Add database indexes for performance
  - Users: Index on `activity_score_7d`, `activity_score_30d`, `last_login_at`, `cm_status`, `cm_synced_at`
  - Organizations: Index on `tier_id`, `is_active`
  - User_product_subscription: Composite index on `user_id, is_active`
  - Segments: Index on `type`, `is_active`
  - Campaigns: Index on `status`, `sent_at`

- [ ] **6.2** Implement caching strategy
  - Cache: Segment counts (60 min), user counts by tier (30 min), top active users (15 min)
  - Redis: Configure Redis for cache driver (update `.env`)
  - Cache tags: Use tags for granular invalidation
  - Invalidation: Clear on tier change, product subscription, campaign send

- [ ] **6.3** Optimize segment query execution
  - Eager loading: Load relationships in segment queries
  - Query caching: Cache complex segment queries for 5 minutes
  - Chunking: Process large segments in chunks of 1000 users
  - Indexes: Ensure all filterable fields are indexed

### Queue System Configuration
- [ ] **6.4** Configure multiple queue priorities
  - Queues: `cm-sync` (high), `cm-campaigns` (medium), `cm-cleanup` (low), `webhooks` (high), `default` (medium)
  - Config: Update `config/queue.php` with queue priorities
  - Workers: Different worker processes for each queue

- [ ] **6.5** Create Supervisor configuration for queue workers
  - File: Create `supervisor-queue-workers.conf` in project root
  - Workers: 3x cm-sync, 2x cm-campaigns, 1x cm-cleanup, 2x webhooks
  - Auto-restart: Configure autorestart and stop signals
  - Logging: Separate log files per queue

- [ ] **6.6** Add queue health monitoring
  - Component: Embed in SyncStatusDashboard
  - Metrics: Jobs waiting, jobs processing, jobs failed, avg wait time
  - Alerts: Visual warning if queue depth > 1000 or failed jobs > 10
  - Actions: Restart workers button (requires server permissions)

### Testing (Pest)
- [ ] **6.7** Create tier/product system tests
  - File: `tests/Feature/TierProductTest.php`
  - Tests: Create tier, assign products to tier, user opt-in/opt-out, tier upgrades
  - Assertions: Database state, relationships, scopes

- [ ] **6.8** Create segmentation engine tests
  - File: `tests/Feature/SegmentationTest.php`
  - Tests: Build segment with rules, execute query, count users, preview users
  - Complex: Nested AND/OR rules, multiple filters, dynamic vs persistent

- [ ] **6.9** Create CM sync tests (mocked)
  - File: `tests/Feature/CampaignMonitorSyncTest.php`
  - Mock: CM API responses with `Http::fake()`
  - Tests: Add subscriber, update subscriber, handle duplicates, retry on failure
  - Assertions: Job dispatched, user synced_at updated, error logging

- [ ] **6.10** Create campaign workflow tests
  - File: `tests/Feature/CampaignWorkflowTest.php`
  - Tests: Recurring campaign scheduled, complex campaign with tag, cleanup job runs
  - Assertions: Campaign created, users tagged, CM API called, cleanup scheduled

- [ ] **6.11** Create Livewire component tests
  - Files: `tests/Feature/Livewire/TierManagerTest.php`, `SegmentBuilderTest.php`, etc.
  - Tests: Component renders, modal opens, form validation, CRUD actions
  - Pest Livewire plugin: Use `Livewire::test()` syntax

### Documentation
- [ ] **6.12** Document HYBRID field mapping strategy
  - File: Update `CDP_WORKFLOWS.md`
  - Content: Field map table, persistent vs dynamic usage, sync schedules
  - Diagrams: Data flow from CDP → CM

- [ ] **6.13** Create admin user guide
  - File: Create `ADMIN_GUIDE.md`
  - Content: How to manage tiers, create segments, build campaigns, monitor syncs
  - Screenshots: Include UI screenshots for each major workflow

- [ ] **6.14** Create end-user guide
  - File: Create `USER_GUIDE.md`
  - Content: How to opt-in/opt-out of products, view activity dashboard, update preferences
  - Self-service: Emphasize user autonomy

- [ ] **6.15** Document Campaign Monitor integration
  - File: Update `CDP_WORKFLOWS.md`
  - Content: Three core workflows with CM API call breakdowns
  - Troubleshooting: Common errors and solutions

### Audit & Compliance
- [ ] **6.16** Create comprehensive audit logging system
  - Table: `audit_logs` (id, user_id, action, model, model_id, before, after, ip, user_agent, created_at)
  - Events: Log tier changes, opt-ins/outs, campaign sends, admin actions
  - Observer: Use Eloquent observers for automatic logging
  - UI: Audit log viewer in admin panel

- [ ] **6.17** Add GDPR compliance features
  - User deletion: Right to be forgotten (delete from CDP + CM)
  - Data export: User can download all their data (JSON/CSV)
  - Consent tracking: Log consent changes with timestamps
  - UI: GDPR dashboard in user settings

### Data Migration
- [ ] **6.18** Build data migration plan for existing 60K users
  - Script: `database/migrations/migrate_existing_users_to_cdp.php`
  - Steps: Assign default tier (Free), set activity scores to 0, backfill last_login_at
  - Products: Auto-subscribe to free products based on existing data
  - Initial sync: Bulk sync all users to CM with persistent fields
  - Timeline: Run migration during off-peak hours, estimated 2-3 hours

---

## 📈 TASK SUMMARY BY TYPE

### UI/Frontend (Livewire + Flux) - 32 tasks
**Admin UI:** 1.5-1.7, 1.8-1.10, 2.4-2.6, 2.9-2.10, 3.4-3.12, 4.15-4.18, 5.5-5.16
**User-Facing UI:** 1.11-1.12, 2.7-2.8
**Navigation:** 1.13-1.14

### Backend (Models, Services, Jobs) - 28 tasks
**Models:** 1.1-1.4, 2.1, 3.1, 5.1-5.2
**Services:** 2.2, 3.2-3.3, 4.2, 4.4, 4.10, 4.14
**Jobs:** 2.3, 4.5-4.6, 4.9, 4.11-4.12, 5.3
**Events/Listeners:** 4.7-4.8
**Webhooks:** 4.13
**Scheduler:** 5.4

### API Integration - 3 tasks
4.1, 4.3, 6.15

### Testing - 5 tasks
6.7-6.11

### Infrastructure - 7 tasks
**Performance:** 6.1-6.3
**Queue:** 6.4-6.6
**Migration:** 6.18

### Documentation & Compliance - 5 tasks
6.12-6.14, 6.16-6.17

---

## 🎯 CRITICAL PATH (Priority Order)

### Week 1: Foundation
1. Tasks 1.1-1.4 (Models & DB)
2. Tasks 1.13-1.14 (Navigation)
3. Tasks 1.5-1.7 (Tier UI)
4. Tasks 1.8-1.10 (Product UI)
5. Tasks 1.11-1.12 (User Product UI)

### Week 2: Activity & Segments
1. Tasks 2.1-2.3 (Activity backend)
2. Tasks 2.4-2.6 (Activity UI)
3. Tasks 3.1-3.3 (Segmentation backend)
4. Tasks 3.4-3.7 (Segment builder UI)

### Week 3: CM Integration (Sync)
1. Tasks 4.1-4.3 (API setup)
2. Tasks 4.4-4.6 (Persistent sync)
3. Tasks 4.7-4.9 (Registration sync)
4. Tasks 4.15-4.18 (Sync UI)

### Week 4: CM Integration (Campaigns)
1. Tasks 4.10-4.12 (Dynamic tags)
2. Tasks 4.13-4.14 (Webhooks)
3. Tasks 5.1-5.4 (Recurring backend)
4. Tasks 5.5-5.7 (Template UI)

### Week 5: Campaign Builder
1. Tasks 5.8-5.12 (Campaign wizard)
2. Tasks 5.13-5.16 (Campaign list & tracking)

### Week 6: Polish & Launch
1. Tasks 6.1-6.6 (Performance & queues)
2. Tasks 6.7-6.11 (Testing)
3. Tasks 6.12-6.17 (Docs & compliance)
4. Task 6.18 (Migration)

---

## 🔄 DEPENDENCIES

### Blocking Dependencies
- **Segment Builder (3.4)** requires: Segment model (3.1), Query builder (3.2)
- **Campaign Builder (5.8)** requires: Segments (3.1-3.7), CM API (4.1-4.3), Templates (5.1)
- **Sync UI (4.15)** requires: All sync jobs (4.4-4.12)
- **Data Migration (6.18)** requires: All Phase 1-5 backend tasks

### Parallel Opportunities
- UI tasks can be built in parallel with backend (use mock data)
- Testing can start as soon as features are complete
- Documentation can be written alongside implementation

---

## 📊 ESTIMATED HOURS

- **Phase 1:** 48 hours (14 tasks × ~3.5h avg)
- **Phase 2:** 36 hours (10 tasks × ~3.6h avg)
- **Phase 3:** 40 hours (12 tasks × ~3.3h avg)
- **Phase 4:** 64 hours (18 tasks × ~3.6h avg)
- **Phase 5:** 56 hours (16 tasks × ~3.5h avg)
- **Phase 6:** 52 hours (18 tasks × ~2.9h avg)

**Total: ~296 hours (~13 weeks at 24 hours/week)**

---

## 📚 RELATED DOCUMENTS

- **Project Plan:** `/Users/ee/Sites/mcp-imports/CDP_PROJECT_PLAN.md`
- **Workflows Guide:** `/Users/ee/Sites/mcp-imports/CDP_WORKFLOWS.md`
- **UI Specifications:** `/Users/ee/Sites/mcp-imports/CDP_UI_SPECIFICATIONS.md` (to be created)

---

## 🛠️ TECH STACK REFERENCE

- **Laravel:** 12.x
- **Livewire:** 3.6.4 (use class-based components for admin, Volt for simple pages)
- **Flux Pro:** 1.0.0 (local package at `/packages/livewire/flux-pro`)
- **Tailwind CSS:** 4.0.7 (use `@theme` directive, not tailwind.config.js)
- **Alpine.js:** Bundled with Livewire
- **Vite:** 7.0.4
- **PHP:** 8.2+
- **Database:** MySQL/MariaDB
- **Queue:** Database (switch to Redis in production)
- **Cache:** Database (switch to Redis in production)

---

## 💡 COMPONENT PATTERNS TO FOLLOW

### Livewire Component Structure
```php
class ExampleManager extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $itemToEdit = null;

    protected $queryString = ['search'];

    public function render() { return view('livewire.example-manager'); }
    public function openModal() { $this->showModal = true; }
    public function save() { /* validation, save, flash message */ }
}
```

### View Pattern (with Flux)
```blade
<div>
    <flux:header>
        <flux:heading>Page Title</flux:heading>
        <flux:button wire:click="openModal">Create New</flux:button>
    </flux:header>

    <flux:input wire:model.live="search" placeholder="Search..." />

    <table class="w-full mt-4">
        @forelse($items as $item)
            <!-- rows -->
        @empty
            <!-- empty state -->
        @endforelse
    </table>

    @if($showModal)
        <!-- Custom Tailwind modal (not Flux modal) -->
    @endif
</div>
```

### Dark Mode Pattern
```blade
<div class="bg-white dark:bg-zinc-800 border border-neutral-200 dark:border-neutral-700">
    <h2 class="text-gray-900 dark:text-white">Title</h2>
    <p class="text-gray-600 dark:text-gray-300">Description</p>
</div>
```

---

**Last Updated:** 2025-11-12
**Status:** Ready for Implementation
**Next Step:** Begin Phase 1, Task 1.1 (Database Schema Design)
