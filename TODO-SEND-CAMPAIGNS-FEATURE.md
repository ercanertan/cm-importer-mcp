# TODO: Send Campaigns from CDP - Implementation Roadmap

**Feature**: Send email campaigns directly from CDP interface using Campaign Monitor API
**Status**: 📋 Planning Phase - Not Yet Implemented
**Estimated Effort**: 19-20 hours
**Priority**: Medium (Future Enhancement)

---

## Overview

This roadmap breaks down the "Send Campaigns from CDP" feature into actionable tasks for the next implementation session. All technical details are documented in `docs/send-campaigns-from-cdp.md`.

**Current State**: Users can tag users in CDP, then must switch to Campaign Monitor to create/send campaigns
**Desired State**: Complete campaign creation and sending directly from CDP interface

---

## Phase 1: Service Layer (4-5 hours)

### Task 1.1: Create CmCampaignService
**File**: `app/Services/CmCampaignService.php`
**Estimated Time**: 2 hours

- [ ] Create new service class with constructor injection
- [ ] Add dependency on `CmNamingService` for consistent naming
- [ ] Implement `isConfigured(): bool` method
- [ ] Add error handling and logging setup
- [ ] Write PHPDoc for all public methods

**Acceptance Criteria**:
- Service instantiates without errors
- Configuration check returns true when API credentials present
- All methods have proper type hints and return types

---

### Task 1.2: Implement Template Methods
**File**: `app/Services/CmCampaignService.php`
**Estimated Time**: 1 hour

- [ ] Implement `fetchTemplates(): array` - Get all templates from CM
- [ ] Add pagination support if needed
- [ ] Include error handling for API failures
- [ ] Log template fetch operations

**Acceptance Criteria**:
- Method returns array of template objects
- Templates include: TemplateID, Name, PreviewURL, ScreenshotURL
- Graceful handling when no templates exist

---

### Task 1.3: Implement Campaign Creation
**File**: `app/Services/CmCampaignService.php`
**Estimated Time**: 1.5 hours

- [ ] Implement `createCampaign(array $params): ?string` method
- [ ] Validate required parameters (name, subject, fromName, fromEmail, replyTo, listId, segmentId, templateId)
- [ ] Create campaign using `CS_REST_Campaigns::create_from_template()`
- [ ] Return campaign ID on success, null on failure
- [ ] Log campaign creation with all parameters

**Acceptance Criteria**:
- Successfully creates campaign in CM
- Returns CM campaign ID
- Validation prevents missing required fields
- Errors logged with context

---

### Task 1.4: Implement Campaign Sending
**File**: `app/Services/CmCampaignService.php`
**Estimated Time**: 30 minutes

- [ ] Implement `sendCampaign(string $campaignId): bool` - Send immediately
- [ ] Implement `scheduleCampaign(string $campaignId, string $datetime): bool` - Schedule for later
- [ ] Implement `sendTestEmail(string $campaignId, array $emails): bool` - Send test
- [ ] Add confirmation date handling for scheduled sends
- [ ] Log all send operations

**Acceptance Criteria**:
- Immediate send works (`send()` returns success)
- Scheduled send accepts future datetime
- Test emails sent to specified addresses
- All operations return boolean success status

---

## Phase 2: Database Schema (1 hour)

### Task 2.1: Create Campaign Drafts Migration
**File**: `database/migrations/YYYY_MM_DD_HHMMSS_create_cm_campaign_drafts_table.php`
**Estimated Time**: 30 minutes

- [ ] Create migration with schema from docs
- [ ] Add columns: id, cm_campaign_id, name, subject, template_id, from_name, from_email, reply_to, segment_id, status, scheduled_at, sent_at, created_by, timestamps
- [ ] Add index on cm_campaign_id (unique)
- [ ] Add index on status for filtering
- [ ] Add index on created_by for user filtering
- [ ] Add foreign key to users table on created_by

**Acceptance Criteria**:
- Migration runs without errors
- All columns created with correct types
- Indexes applied successfully
- Foreign key constraint works

---

### Task 2.2: Create CmCampaignDraft Model
**File**: `app/Models/CmCampaignDraft.php`
**Estimated Time**: 30 minutes

- [ ] Create Eloquent model with fillable fields
- [ ] Add casts for dates (scheduled_at, sent_at)
- [ ] Add relationship to User model (`belongsTo`)
- [ ] Create scopes: `draft()`, `scheduled()`, `sent()`
- [ ] Add accessor for status badge color
- [ ] Write PHPDoc for relationships

**Acceptance Criteria**:
- Model can be instantiated
- Relationships work bidirectionally
- Scopes filter correctly
- Dates cast to Carbon instances

---

## Phase 3: Livewire Component Enhancement (3-4 hours)

### Task 3.1: Add Component Properties
**File**: `app/Livewire/Admin/Campaigns/CampaignManager.php`
**Estimated Time**: 30 minutes

- [ ] Add public properties for campaign form
- [ ] Add validation rules array
- [ ] Add computed property for templates list
- [ ] Add property for selected template preview
- [ ] Add loading states for async operations

**Properties to Add**:
```php
// Campaign creation
public $sendCampaignName = '';
public $sendSubject = '';
public $sendFromName = '';
public $sendFromEmail = '';
public $sendReplyTo = '';
public $selectedTemplateId = '';
public $sendDate = ''; // For scheduling
public $sendTime = ''; // For scheduling
public $testEmails = ''; // Comma-separated

// State
public $showTemplatePreview = false;
public $sendingCampaign = false;
public $schedulingCampaign = false;
public $sendingTest = false;
```

---

### Task 3.2: Implement Campaign Methods
**File**: `app/Livewire/Admin/Campaigns/CampaignManager.php`
**Estimated Time**: 2 hours

- [ ] Implement `loadTemplates()` - Fetch CM templates
- [ ] Implement `previewTemplate($templateId)` - Show template preview
- [ ] Implement `createAndSendCampaign()` - Create + send immediately
- [ ] Implement `createAndScheduleCampaign()` - Create + schedule
- [ ] Implement `sendTestCampaign()` - Send test to emails
- [ ] Add validation for all methods
- [ ] Add success/error flash messages
- [ ] Handle API errors gracefully

**Acceptance Criteria**:
- Templates load on mount
- Validation prevents invalid submissions
- Success creates campaign in CM
- Flash messages appear in UI
- Errors logged and displayed to user

---

### Task 3.3: Add Computed Properties
**File**: `app/Livewire/Admin/Campaigns/CampaignManager.php`
**Estimated Time**: 30 minutes

- [ ] Add `#[Computed] public function campaignDrafts()` - Paginated drafts
- [ ] Add `#[Computed] public function sentCampaigns()` - Sent campaigns
- [ ] Add filtering by status
- [ ] Add sorting by created_at

---

### Task 3.4: Dependency Injection
**File**: `app/Livewire/Admin/Campaigns/CampaignManager.php`
**Estimated Time**: 30 minutes

- [ ] Inject `CmCampaignService` in boot method
- [ ] Store as protected property
- [ ] Use throughout component methods

---

## Phase 4: UI Implementation (6-7 hours)

### Task 4.1: Add "Send Campaign" Tab
**File**: `resources/views/livewire/admin/campaigns/campaign-manager.blade.php`
**Estimated Time**: 30 minutes

- [ ] Add new tab: `<flux:tab name="send" icon="paper-airplane">Send Campaign (CM)</flux:tab>`
- [ ] Add info panel explaining this creates campaigns in CM
- [ ] Add connection status check requirement
- [ ] Position after "Quick Tag (CDP)" tab

---

### Task 4.2: Create Campaign Builder Form
**File**: `resources/views/livewire/admin/campaigns/campaign-manager.blade.php`
**Estimated Time**: 3 hours

- [ ] Create form with all required fields
- [ ] Add template selector dropdown with previews
- [ ] Add campaign details section (name, subject, from, reply-to)
- [ ] Add segment selector (existing CDP segments)
- [ ] Add send options (immediate, schedule, test)
- [ ] Add date/time picker for scheduling
- [ ] Add test email input (comma-separated)
- [ ] Style consistently with existing tabs

**Form Fields**:
```blade
<flux:field>
    <flux:label>Campaign Name</flux:label>
    <flux:input wire:model="sendCampaignName" placeholder="November Newsletter 2024" />
    <flux:error name="sendCampaignName" />
</flux:field>

<flux:field>
    <flux:label>Email Subject</flux:label>
    <flux:input wire:model="sendSubject" placeholder="Check out what's new this month" />
    <flux:error name="sendSubject" />
</flux:field>

<!-- Template selector with preview -->
<flux:field>
    <flux:label>Email Template</flux:label>
    <flux:select wire:model.live="selectedTemplateId">
        <option value="">Select a template...</option>
        @foreach($this->templates as $template)
            <option value="{{ $template->TemplateID }}">{{ $template->Name }}</option>
        @endforeach
    </flux:select>
    @if($selectedTemplateId)
        <flux:button wire:click="previewTemplate({{ $selectedTemplateId }})" variant="ghost" size="sm">
            Preview Template
        </flux:button>
    @endif
</flux:field>

<!-- From/Reply fields -->
<!-- Segment selector -->
<!-- Send options (radio buttons) -->
<!-- Conditional date/time picker -->
<!-- Test emails textarea -->
```

---

### Task 4.3: Add Action Buttons
**File**: `resources/views/livewire/admin/campaigns/campaign-manager.blade.php`
**Estimated Time**: 1 hour

- [ ] Add "Create & Send Now" button
- [ ] Add "Schedule Campaign" button (conditional on date/time)
- [ ] Add "Send Test Email" button
- [ ] Add loading states to all buttons
- [ ] Add confirmation modals for sending
- [ ] Style with Flux components

**Button Implementation**:
```blade
<div class="flex gap-3">
    <flux:button
        wire:click="createAndSendCampaign"
        variant="primary"
        icon="paper-airplane"
        wire:loading.attr="disabled"
        wire:target="createAndSendCampaign"
    >
        <span wire:loading.remove wire:target="createAndSendCampaign">Create & Send Now</span>
        <span wire:loading wire:target="createAndSendCampaign">Sending...</span>
    </flux:button>

    <flux:button
        wire:click="sendTestCampaign"
        variant="ghost"
        icon="beaker"
        wire:loading.attr="disabled"
    >
        Send Test Email
    </flux:button>
</div>
```

---

### Task 4.4: Template Preview Modal
**File**: `resources/views/livewire/admin/campaigns/campaign-manager.blade.php`
**Estimated Time**: 1 hour

- [ ] Create modal to show template preview
- [ ] Display template name and screenshot
- [ ] Add "Use This Template" button
- [ ] Add close button
- [ ] Make responsive

---

### Task 4.5: Campaign Drafts List
**File**: `resources/views/livewire/admin/campaigns/campaign-manager.blade.php`
**Estimated Time**: 1.5 hours

- [ ] Add section below form showing recent drafts
- [ ] Display in table/list format
- [ ] Show: name, subject, status, scheduled date, created date
- [ ] Add status badges (draft, scheduled, sent)
- [ ] Add actions (edit, delete, view in CM)
- [ ] Add pagination
- [ ] Add empty state

---

## Phase 5: Integration & Tracking (2-3 hours)

### Task 5.1: Link Sent Campaigns to Stats
**File**: `app/Services/CmCampaignService.php`
**Estimated Time**: 1 hour

- [ ] After successful send, create record in `cm_campaign_drafts`
- [ ] Store CM campaign ID for tracking
- [ ] Update status to 'sent'
- [ ] Record sent_at timestamp
- [ ] Link to existing campaign stats import

**Implementation**:
```php
// In createAndSendCampaign method
$campaignId = $this->cmCampaignService->createCampaign([...]);
if ($campaignId && $this->cmCampaignService->sendCampaign($campaignId)) {
    CmCampaignDraft::create([
        'cm_campaign_id' => $campaignId,
        'name' => $this->sendCampaignName,
        'subject' => $this->sendSubject,
        'status' => 'sent',
        'sent_at' => now(),
        'created_by' => auth()->id(),
    ]);
}
```

---

### Task 5.2: Auto-Import Stats After Send
**File**: `app/Livewire/Admin/Campaigns/CampaignManager.php`
**Estimated Time**: 1 hour

- [ ] After sending campaign, dispatch job to import stats
- [ ] Wait 5 minutes before first import (allow CM to process)
- [ ] Schedule recurring imports (hourly for first 24h, then daily)
- [ ] Show "Stats importing..." indicator in UI

**Job Implementation**:
```php
// Create new job: app/Jobs/ImportCampaignStatsJob.php
dispatch(new ImportCampaignStatsJob($campaignId))->delay(now()->addMinutes(5));
```

---

### Task 5.3: Notification System
**File**: Multiple files
**Estimated Time**: 1 hour

- [ ] Create notification for successful campaign send
- [ ] Create notification for scheduled campaigns
- [ ] Create notification for failed sends
- [ ] Send via database notification channel
- [ ] Display in UI notification center (if exists)

---

## Phase 6: Testing (3-4 hours)

### Task 6.1: Unit Tests
**File**: `tests/Unit/Services/CmCampaignServiceTest.php`
**Estimated Time**: 2 hours

- [ ] Test `isConfigured()` with and without credentials
- [ ] Test `fetchTemplates()` success and failure
- [ ] Test `createCampaign()` with valid params
- [ ] Test `createCampaign()` with missing params
- [ ] Test `sendCampaign()` success
- [ ] Test `scheduleCampaign()` with valid datetime
- [ ] Test `sendTestEmail()` with valid emails
- [ ] Mock all CS_REST_* SDK calls
- [ ] Assert correct API methods called
- [ ] Assert error logging works

**Test Structure**:
```php
it('creates campaign with valid parameters', function () {
    // Mock CS_REST_Campaigns
    // Call createCampaign()
    // Assert campaign ID returned
    // Assert correct API calls made
});

it('returns null when campaign creation fails', function () {
    // Mock API failure
    // Call createCampaign()
    // Assert null returned
    // Assert error logged
});
```

---

### Task 6.2: Feature Tests
**File**: `tests/Feature/Livewire/CampaignManagerTest.php`
**Estimated Time**: 1.5 hours

- [ ] Test campaign form renders
- [ ] Test templates load on mount
- [ ] Test template preview works
- [ ] Test validation prevents empty submissions
- [ ] Test successful campaign send creates draft record
- [ ] Test flash messages appear
- [ ] Test scheduled campaigns save correct datetime
- [ ] Test test emails send to correct addresses

**Test Structure**:
```php
it('creates and sends campaign successfully', function () {
    Livewire::test(CampaignManager::class)
        ->set('sendCampaignName', 'Test Campaign')
        ->set('sendSubject', 'Test Subject')
        ->set('selectedTemplateId', 'template123')
        ->call('createAndSendCampaign')
        ->assertHasNoErrors()
        ->assertDispatched('campaign-sent');

    expect(CmCampaignDraft::count())->toBe(1);
});
```

---

### Task 6.3: Manual QA Testing
**Estimated Time**: 30 minutes

- [ ] Test full workflow in browser
- [ ] Verify campaign appears in Campaign Monitor
- [ ] Verify test emails received
- [ ] Test scheduled campaign appears in CM with correct date
- [ ] Test error handling with invalid credentials
- [ ] Test UI responsiveness
- [ ] Test loading states
- [ ] Verify stats import after send

---

## Dependencies & Prerequisites

**Before Starting Implementation:**
- [ ] Campaign Monitor API credentials configured (CM_API_KEY, CM_CLIENT_ID)
- [ ] At least one template created in Campaign Monitor
- [ ] Existing segments/lists in Campaign Monitor
- [ ] All Phase 1-5 tasks from previous sessions completed

**External Dependencies:**
- Campaign Monitor PHP SDK v7.1 (already installed)
- Livewire 3.6.4 (already installed)
- Flux 2.6 + Flux Pro 1.0 (already installed)

---

## Task Summary by Time Estimate

| Phase | Tasks | Estimated Time |
|-------|-------|----------------|
| Phase 1: Service Layer | 4 tasks | 4-5 hours |
| Phase 2: Database Schema | 2 tasks | 1 hour |
| Phase 3: Livewire Component | 4 tasks | 3-4 hours |
| Phase 4: UI Implementation | 5 tasks | 6-7 hours |
| Phase 5: Integration & Tracking | 3 tasks | 2-3 hours |
| Phase 6: Testing | 3 tasks | 3-4 hours |
| **TOTAL** | **21 tasks** | **19-23 hours** |

---

## Implementation Order

**Recommended approach:**

1. **Day 1 (6-7 hours)**: Phase 1 + Phase 2 - Complete service layer and database
2. **Day 2 (6-7 hours)**: Phase 3 + Start Phase 4 - Component logic and basic UI
3. **Day 3 (7-9 hours)**: Finish Phase 4 + Phase 5 + Phase 6 - Polish UI, integrate, test

**Alternative approach (if splitting across multiple days):**

1. Service layer (Phase 1)
2. Database + Model (Phase 2)
3. Component properties + methods (Phase 3)
4. UI tab + form (Phase 4.1 - 4.3)
5. UI preview + drafts (Phase 4.4 - 4.5)
6. Integration (Phase 5)
7. Testing (Phase 6)

---

## Success Criteria

**Feature is complete when:**
- [ ] User can select CM template from dropdown
- [ ] User can fill out campaign details in form
- [ ] User can send campaign immediately from CDP
- [ ] User can schedule campaign for future date
- [ ] User can send test emails to verify content
- [ ] Campaign appears in Campaign Monitor after creation
- [ ] Campaign stats auto-import after sending
- [ ] Draft campaigns tracked in database
- [ ] All validation prevents invalid submissions
- [ ] Error messages clear and actionable
- [ ] Loading states prevent duplicate submissions
- [ ] All tests pass (unit + feature)

---

## Known Limitations & Future Enhancements

**Current Limitations:**
- Uses existing CM templates only (no custom HTML editor)
- No content preview/editing before send
- No A/B testing support
- No recipient list preview from CM
- No campaign duplication feature

**Future Enhancements:**
1. HTML editor integration for custom content
2. Content preview with merge tags
3. A/B testing for subject lines
4. Show recipient count from CM before sending
5. Campaign duplication/cloning
6. Campaign performance dashboard
7. Automated follow-up campaigns
8. Email template builder in CDP

---

## Related Documentation

- `docs/send-campaigns-from-cdp.md` - Complete technical specification
- `docs/campaign-stats-import.md` - Campaign statistics import guide
- `docs/campaign-manager-ui-implementation.md` - UI implementation guide
- `docs/bulk-tag-sync-implementation.md` - Tag sync architecture

---

**Created**: November 11, 2024
**Status**: 📋 Ready for Implementation
**Next Session**: Start with Phase 1 - Service Layer
