# TODO - Campaign Monitor Integration

## Current Sprint: Campaign Monitor Integration & User Engagement Tracking

### CRITICAL: Bulk Tag Sync Strategy

**Problem Identified**: Individual tag syncing via observers would cause API call storms
- Organization tier change (2,847 users) = 2,847 API calls ❌
- Bulk import (10,000 users) = 10,000 API calls ❌
- Engagement recalc (60,000 users) = 60,000 API calls ❌

**Solution**: Mark for sync + Scheduled batch job
- Organization tier change = 6 API calls ✅
- Bulk import = ~20 API calls ✅
- Engagement recalc = ~120 API calls ✅

---

### High Priority - Bulk Tag Sync Implementation ✅ (COMPLETED)

- [x] Create migration for tag sync tracking ✅
  - Added `cm_tags_need_sync` boolean to users table
  - Added `cm_tags_synced_at` timestamp to users table
  - Added index for efficient querying
  - **File**: `database/migrations/2025_11_11_100346_add_tag_sync_tracking_to_users_table.php`

- [x] Create `SyncCampaignMonitorTags` command ✅
  - Finds users with `cm_tags_need_sync = true`
  - Calculates expected tags using CmNamingService
  - Processes in batches of 500 users
  - Scheduled every 10 minutes
  - **File**: `app/Console/Commands/SyncCampaignMonitorTags.php` (256 lines)
  - **Usage**: `php artisan cm:sync-tags [--dry-run] [--limit=N]`

- [x] Update `UserObserver` to mark for sync ✅
  - Detects tier/engagement/status changes
  - Marks users: `cm_tags_need_sync = true` (no immediate API call)
  - Still syncs custom fields immediately (fast operations)
  - **File**: `app/Observers/UserObserver.php` (modified)

- [x] Update `OrganizationObserver` to mark users for sync ✅
  - When org tier changes: marks all users `cm_tags_need_sync = true`
  - No immediate tag syncs (prevents API storms)
  - **File**: `app/Observers/OrganizationObserver.php` (modified)

- [x] Scheduled task configured ✅
  - Command scheduled every 10 minutes
  - With overlap prevention
  - Background execution
  - Success/failure logging
  - **File**: `routes/console.php`

- [x] Tag calculation integrated ✅
  - `CmNamingService::getUserPermanentTags()` - Gets expected tags
  - Based on tier, engagement_score, cm_status
  - Returns human-readable tags: `[Tier] Paid Premium`, `[Engagement] High (70-100)`, `[Status] Active`

**Result**: 99.8% reduction in API calls for bulk operations!

---

### High Priority - Recurring Campaign Architecture

- [ ] Create `campaign_templates` table migration
  - Store daily/weekly/monthly campaign definitions
  - Link to CM segment IDs

- [ ] Create `campaign_sends` table migration
  - Track all campaign sends
  - Store stats (opens, clicks, bounces)

- [ ] Create recurring campaign commands
  - `SendDailyDigest` - For paid_pro tier
  - `SendWeeklyNewsletter` - For all active users
  - `SendMonthlyReport` - For enterprise tier

- [ ] Create `SetupRecurringCampaignSegments` command
  - One-time setup of permanent segments in CM
  - Based on tags: tier:*, status:*, engagement:*

- [ ] Build admin UI for campaign management
  - List all campaign templates
  - Create/edit/delete campaigns
  - View campaign analytics
  - Send one-off campaigns
  - No CM login required!

---

### High Priority - Human-Readable Implementation (✅ COMPLETED)

- [x] Create `CmNamingService` for consistent human-readable naming
  - File: `app/Services/CmNamingService.php` ✅
  - Format tier tags: `[Tier] Paid Premium`
  - Format engagement tags: `[Engagement] High (70-100)`
  - Format status tags: `[Status] Active`
  - Format product tags: `[Product] Premium Content`
  - Format event tags: `[Event] Annual Conference 2025`
  - Format behavior tags: `[Behavior] Frequent Attendee`
  - Generate segment names with user counts and dates
  - Generate campaign names with categories and dates

- [x] Update `config/campaign-monitor.php` with human-readable conventions ✅
  - Added 13 core fields with human-readable names
  - Added tag category prefixes (tier, engagement, status, product, event, behavior, organization)
  - Added tier display names (Free, Paid Pro, Paid Premium, Enterprise)
  - Added engagement level names with score ranges
  - Added status display names
  - Added campaign name templates

### Medium Priority - Service Integration ✅ (COMPLETED)

- [x] Update services to use `CmNamingService` ✅
  - `CampaignTagService.php` - Dependency injection added
  - `CmSyncService.php` - Already using human-readable naming
  - `UserObserver.php` - Uses CmNamingService for tier/engagement tags
  - `OrganizationObserver.php` - Uses mark-for-sync pattern

- [x] Review and finalize Campaign Monitor field setup command ✅
  - File: `app/Console/Commands/SetupCampaignMonitorFields.php`
  - Uses human-readable field names from config
  - Status: Complete and ready for production

- [x] Verify all migrations are ready ✅
  - All migration files verified in `database/migrations/`
  - Proper indexes and foreign keys in place
  - Rollback functionality reviewed

- [x] Review and test bulk job implementations ✅
  - `BulkSyncOrganizationUsersJob.php` - Tested
  - `ClearTagBulkJob.php` - Tested (388 lines, ~20 tests)
  - `TagUsersBulkJob.php` - Tested (244 lines, ~15 tests)
  - Error handling and logging verified

- [x] Ensure all observers are properly registered ✅
  - `UserObserver.php` - Registered and tested
  - `OrganizationObserver.php` - Registered and tested
  - Verified registration in `AppServiceProvider.php`

- [x] Run test suite to verify everything works ✅
  - Executed: `vendor/bin/pest`
  - CampaignTagServiceTest: 27 tests passed (fixed dependency injection)
  - CmSyncServiceTest: 10 tests passed
  - EngagementMetricsServiceTest: 22 tests passed
  - All major test suites passing

- [ ] Review documentation
  - `docs/campaign-monitor-sync-strategy.md` ✓ (exists)
  - `docs/organization-tier-handling.md` ✓ (exists)
  - `docs/50-field-limit-solution.md` ✓ (exists)
  - `docs/recurring-campaigns-architecture.md` ✓ (exists)
  - `docs/bulk-tag-sync-strategy.md` ✓ (exists)
  - `docs/human-readable-cm-strategy.md` ✓ (exists)
  - Ensure docs match current implementation

- [ ] Review and test service implementations
  - `CampaignMonitorImportService.php`
  - `CampaignTagService.php`
  - `CmSyncService.php`
  - `EngagementMetricsService.php`
  - `CmNamingService.php` ✅ (newly created)

- [ ] Verify model implementations
  - `User.php` - tier and engagement fields
  - `EmailEngagement.php`
  - `Event.php`
  - `EventAttendance.php`

---

### Low Priority / Future Enhancements

- [ ] Add queue monitoring dashboard
- [ ] Implement sync verification command (`php artisan cm:verify-sync`)
- [ ] Add retry mechanism for failed syncs
- [ ] Create admin notifications for bulk operations
- [ ] Add job deduplication for organization tier changes
- [ ] Implement rate limiting for API calls
- [ ] Add comprehensive logging dashboard
- [ ] Create webhook handler for CM campaign statistics
- [ ] Build email template editor in admin UI
- [ ] Add A/B testing support for campaigns

---

## Backlog

- [ ] Performance optimization for large organizations
- [ ] Add webhook support for Campaign Monitor events
- [ ] Implement two-way sync (CM → Laravel) - Optional
- [ ] Create admin UI for managing sync settings
- [ ] Add export functionality for engagement metrics
- [ ] Build campaign ROI tracking
- [ ] Create user segment builder UI
- [ ] Add campaign template library

---

## Architecture Documents (✓ Complete)

1. ✅ **campaign-monitor-sync-strategy.md** - Bulk import vs individual sync
2. ✅ **organization-tier-handling.md** - Org tier change handling
3. ✅ **50-field-limit-solution.md** - THE CORE PROBLEM SOLUTION
4. ✅ **recurring-campaigns-architecture.md** - Daily/weekly/monthly campaigns
5. ✅ **bulk-tag-sync-strategy.md** - Avoiding API call storms
6. ✅ **campaign-tag-workflow.md** - One-off campaign workflow
7. ✅ **implementation-status.md** - Complete inventory of what's built
8. ✅ **complex-query-capabilities.md** - Complex filtering (YOUR USE CASE!)
9. ✅ **webhook-implementation-summary.md** - Webhook integration details
10. ✅ **test-coverage-summary.md** - Complete test review (2,600+ lines)
11. ✅ **human-readable-cm-strategy.md** - Human-readable naming for CM admins

## Test Coverage (✓ Verified)

**Total**: 2,600+ lines of tests, ~164 test cases

1. ✅ **ClearTagBulkJobTest.php** (388 lines, ~20 tests)
   - Tag cleanup after campaigns
   - Batch processing (500, 1000, 2500 users)
   - Optional CM sync

2. ✅ **TagUsersBulkJobTest.php** (244 lines, ~15 tests)
   - Bulk tag syncing to CM
   - Import API integration
   - Batch processing (1000/batch)

3. ✅ **EmailEngagementTest.php** (292 lines, ~18 tests)
   - Email interaction tracking
   - Scopes (opens, clicks, bounces, recent, forCampaign)
   - JSON/datetime casting

4. ✅ **EventAttendanceTest.php** (353 lines, ~22 tests)
   - User event attendance tracking
   - Auto-timestamps (registered_at, attended_at, cancelled_at)
   - Unique constraints, scopes
   - Helper methods (hasAttended, isActive, etc.)

5. ✅ **EventTest.php** (352 lines, ~24 tests)
   - Event management
   - Auto-slug generation
   - Scopes (published, upcoming, past, ofType)
   - Helper methods (isUpcoming, hasStarted, etc.)

6. ✅ **CampaignTagServiceTest.php** (387 lines, ~25 tests)
   - Complex query filtering ← YOUR USE CASE TESTED!
   - Tag generation, bulk tagging
   - Helper methods (tagHighlyEngagedByTier, tagEventAttendeesActive, tagDisengagedUsers)
   - Tag statistics

7. ✅ **CmSyncServiceTest.php** (159 lines, ~12 tests)
   - User sync to CM
   - Field-level change detection
   - Bulk import, segment creation/deletion

8. ✅ **EngagementMetricsServiceTest.php** (425 lines, ~28 tests)
   - Engagement score calculation (0-100)
   - Score components (open rate, click rate, recency)
   - Bulk updates, categorization, trends

---

## Notes

- All new files are currently untracked in git
- Modified files include composer dependencies and config
- Branch: `ddd`
- Last update: 2025-11-10

### Key Design Decisions:

1. **Custom Fields (13 max)** vs **Tags (unlimited)** for 50-field limit
2. **Permanent tags** for recurring campaigns vs **temp_campaign_tag** for one-offs
3. **Mark for sync** vs immediate sync to avoid API storms
4. **Scheduled batch job** (every 5-15 min) for tag syncing
5. **One-way sync** (Laravel → CM) only
6. **CDP manages everything** - no CM login required
7. **Human-readable everything** - CM admins must understand all data without training
   - Custom fields: "Engagement Score (0-100)" not "eng_score"
   - Tags: "[Tier] Paid Premium" not "tier_pp"
   - Segments: "[Recurring] Paid Pro - Daily Digest" not "seg_123"
   - Campaigns: "[Event Alumni] We Miss You - Nov 10, 2024" not "camp_123"

---

*This file is automatically maintained. Update as tasks are completed.*
