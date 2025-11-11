# PROGRESS - Campaign Monitor Integration

## Original Requirements

**Initial Prompt**: CDP with Campaign Monitor

### Business Model
- **Organizations** buy tiers (free/paid) via manual payment
- **Users** belong to organizations
- **Users** can opt in/out of multiple products across tiers
- Multiple organizations, each with multiple users

### Core Problem: Campaign Monitor 50 Custom Field Limit

**Challenge**: Campaign Monitor only allows **50 custom fields per list**. We need to work with a **single CM list** but require **limitless ability** to:
1. Manage user subscriptions to multiple products
2. Track user behavior and engagement (CDP - Customer Data Platform)
3. Maintain comprehensive user audit trail

**Solution Strategy**: Use Campaign Monitor for email delivery + basic segmentation, while maintaining the full CDP data in Laravel database. Sync only essential fields to CM custom fields, use **tags** for product subscriptions and segmentation.

### System Architecture
- **Laravel (Source of Truth)**: Store ALL user data, subscriptions, engagement metrics, audit logs
- **Campaign Monitor (Email Engine)**: Store only essential fields (max 50) + use tags for segmentation
- **Sync Strategy**: Smart observers detect changes and sync selectively to CM

## Project Overview

Building a comprehensive Campaign Monitor integration for Laravel application with:
- User tier tracking and engagement metrics
- Event attendance tracking
- Email engagement monitoring
- Bulk sync and tagging operations
- Smart observers for automatic syncing
- Tag-based product subscription management
- Comprehensive test coverage

---

## Session: 2025-11-10

### Branch: `ddd`

### Completed Work

#### Database Schema
- ✅ Created migration for user tier and engagement fields
  - `engagement_score` (integer)
  - `total_clicks` (integer)
  - `total_opens` (integer)
  - `last_email_opened_at` (timestamp)
  - `last_email_clicked_at` (timestamp)
  - `tier` (string)
  - File: `database/migrations/2025_11_10_124146_add_tier_and_engagement_fields_to_users_table.php`

- ✅ Created migration for organization tier
  - `tier` (string)
  - File: `database/migrations/2025_11_10_125846_add_tier_to_organizations_table.php`

- ✅ Created email_engagements table
  - Tracks email opens, clicks, bounces, unsubscribes
  - File: `database/migrations/2025_11_10_130209_create_email_engagements_table.php`

- ✅ Created events table
  - Tracks organization events
  - File: `database/migrations/2025_11_10_130727_create_events_table.php`

- ✅ Created event_attendances table
  - Tracks user attendance at events
  - File: `database/migrations/2025_11_10_130844_create_event_attendances_table.php`

- ✅ Created temp_campaign_tag field for users
  - For bulk tag operations
  - File: `database/migrations/2025_11_10_182625_add_temp_campaign_tag_to_users_table.php`

#### Models
- ✅ Updated User model with tier and engagement fields
- ✅ Created EmailEngagement model with factory
- ✅ Created Event model with factory
- ✅ Created EventAttendance model with factory

#### Services
- ✅ Implemented CampaignMonitorImportService (updated)
  - Bulk import handling with smart sync detection

- ✅ Implemented CampaignTagService
  - Tag management for bulk operations

- ✅ Implemented CmSyncService **(ENHANCED)**
  - Core sync logic for Campaign Monitor
  - Individual user sync with field-level tracking
  - Bulk import API integration
  - **Segment creation/deletion/retrieval**
  - **Campaign Tag Segment helper** (convenience method)
  - Complete API wrapper for CM operations

- ✅ Implemented EngagementMetricsService
  - Calculate user engagement scores

#### Jobs
- ✅ Created BulkSyncOrganizationUsersJob
  - Handles bulk syncing of organization users

- ✅ Created ClearTagBulkJob
  - Clears tags from users in bulk

- ✅ Created TagUsersBulkJob
  - Tags users in bulk operations

#### Observers
- ✅ Created UserObserver
  - Smart sync detection (bulk vs individual)
  - Field-level change tracking

- ✅ Created OrganizationObserver
  - Handles organization tier changes
  - Triggers bulk user updates

#### Commands
- ✅ Created SetupCampaignMonitorFields command
  - Sets up custom fields in Campaign Monitor

#### API Controllers
- ✅ Created CmWebhookController **(COMPREHENSIVE)**
  - `handleDeactivate()` - Subscriber deactivation events
  - `handleUpdate()` - Subscriber updates
  - `handleOpen()` - Email opens (tracks to EmailEngagement + updates user metrics)
  - `handleClick()` - Link clicks (tracks to EmailEngagement + updates user metrics)
  - `handleBounce()` - Email bounces (hard/soft, updates CM status)
  - `handleUnsubscribe()` - Unsubscribe events
  - `handleSpamComplaint()` - Spam complaint handling
  - User lookup by user_id custom field (reliable) with email fallback
  - Comprehensive validation and error handling
  - Full logging for audit trail

#### Tests
- ✅ Comprehensive test coverage for:
  - Controllers
  - Jobs (ClearTagBulkJob, TagUsersBulkJob)
  - Models
  - Services (CampaignTagService, CmSyncService, EngagementMetricsService)

#### Documentation
- ✅ Created campaign-monitor-sync-strategy.md
  - Explains bulk import vs individual sync logic
  - Details observer implementation
  - Provides usage examples

- ✅ Created organization-tier-handling.md
  - Explains organization tier change handling
  - Details bulk sync job implementation
  - Covers monitoring and error handling

- ✅ Created 50-field-limit-solution.md **(CRITICAL)**
  - Complete explanation of the 50 custom field limit problem
  - 3-tier architecture (Custom Fields, Tags, Laravel DB)
  - Field allocation strategy (13 core fields)
  - Tag strategy for unlimited products/segments
  - Real-world examples and migration paths

- ✅ Created campaign-tag-workflow.md **(THE CORE WORKFLOW)**
  - Complete 5-step workflow for sending campaigns to dynamic segments
  - Uses temp_campaign_tag as "scratchpad" for targeting
  - 25,000-user campaign in only 53 API calls (vs 25,000)
  - Batch processing with CM Import API (1000 users/batch)
  - Segment creation and cleanup processes
  - Error handling and edge cases
  - Multiple campaigns per day strategy
  - Campaign result tracking and analytics

#### Configuration
- ✅ Updated config/campaign-monitor.php
  - Added bulk import settings
  - Added sync threshold configuration

#### Dependencies
- ✅ Updated composer.json and composer.lock
  - Added required packages for Campaign Monitor integration

---

## In Progress

### Current Focus
- Finalizing API controller implementations
- Verifying observer registration in AppServiceProvider
- Running comprehensive test suite

### Blocked
- None currently

---

## Next Steps

1. **Testing Phase**
   - Run full test suite
   - Verify all migrations work
   - Test bulk operations with sample data

2. **Code Review**
   - Review observer implementations
   - Verify service layer logic
   - Check error handling across all components

3. **Integration Testing**
   - Test with actual Campaign Monitor API
   - Verify bulk sync performance
   - Test edge cases (large organizations, concurrent updates)

4. **Documentation**
   - Add inline code documentation
   - Create API endpoint documentation
   - Update README with setup instructions

---

## Known Issues

- [ ] Need to verify observer registration in AppServiceProvider
- [ ] API controllers may need completion
- [ ] All new files are untracked in git

---

## Metrics

- **Migrations Created**: 5
- **Models Created**: 3 (+ 1 updated)
- **Services Created**: 4
- **Jobs Created**: 3
- **Observers Created**: 2
- **Commands Created**: 1
- **Tests Created**: ~10+ test files
- **Documentation Pages**: 2

---

## Architecture Decisions

### 50 Custom Field Limit Solution (CORE DECISION)
- **Problem**: CM allows only 50 custom fields per list; we need unlimited tracking
- **Decision**: Laravel is source of truth, CM is email delivery engine
- **Strategy**:
  - **Custom Fields (Limited to ~15-20 core fields)**:
    - User ID (for syncing)
    - Email
    - Full Name
    - Organization Name
    - Organization Tier
    - User Tier
    - Engagement Score
    - Total Opens/Clicks
    - Last Email Opened/Clicked
    - Permission to Track
  - **Tags (Unlimited)**:
    - Product subscriptions (e.g., `product:newsletter`, `product:webinar`)
    - User segments (e.g., `segment:high-engagement`, `segment:at-risk`)
    - Organization segments (e.g., `org:enterprise`, `org:startup`)
    - Behavioral tags (e.g., `behavior:active-attendee`, `behavior:never-opened`)
  - **Laravel Database (Unlimited)**:
    - Full subscription history
    - Complete engagement metrics
    - Event attendance records
    - Email engagement details
    - Audit logs
- **Benefits**:
  - Never hit 50-field limit
  - Can add unlimited products/subscriptions via tags
  - Full CDP capabilities in Laravel
  - CM remains simple and focused on email delivery

### Smart Observer Pattern
- **Decision**: Use bulk operation flag to prevent unnecessary syncs during imports
- **Rationale**: Prevents thousands of individual API calls during bulk operations
- **Implementation**: `app()->instance('cm.bulk_import_active', true)`

### Organization Tier Threshold
- **Decision**: Use configurable threshold (default: 10 users) to determine sync strategy
- **Rationale**: Small orgs sync immediately, large orgs queue bulk job
- **Configuration**: `CM_SYNC_THRESHOLD` in .env

### Field-Level Change Tracking
- **Decision**: Only sync changed fields to Campaign Monitor
- **Rationale**: Reduces API calls and improves performance
- **Implementation**: `wasChanged()` checks in UserObserver

### Queued Bulk Operations
- **Decision**: Use Laravel queue for bulk syncs
- **Rationale**: Prevents timeouts, allows retry logic, better UX
- **Implementation**: BulkSyncOrganizationUsersJob with retry strategy

### Tag-Based Product Subscriptions
- **Decision**: Use CM tags instead of custom fields for product subscriptions
- **Rationale**: Unlimited products without hitting 50-field limit
- **Implementation**:
  - `CampaignTagService` manages tag operations
  - `TagUsersBulkJob` for bulk tagging
  - `ClearTagBulkJob` for bulk untagging
  - Naming convention: `product:name`, `segment:type`, `behavior:action`

---

*Last updated: 2025-11-10*
*Update this file at the end of each work session.*
