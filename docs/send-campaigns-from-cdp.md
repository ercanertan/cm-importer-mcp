# Send Email Campaigns Directly from CDP

## Overview

This document describes how to implement the ability to **create and send email campaigns directly from your CDP interface** instead of manually going to Campaign Monitor to complete the workflow.

**Current State**: Your CDP can tag users and sync to Campaign Monitor, but you must go to CM to create and send the actual email campaign.

**Desired State**: Complete the entire workflow in CDP - from user selection to campaign creation to sending - without leaving your application.

---

## Current vs Desired Workflow

### Current Workflow (Manual CM Steps)

```
1. CDP: Tag users with segment builder ✅
2. CDP: Tags sync to Campaign Monitor (automatic) ✅
3. 👉 GO TO CAMPAIGN MONITOR (manual switch)
4. CM: Create new segment with temp_campaign_tag filter
5. CM: Create new campaign
6. CM: Select segment as audience
7. CM: Design email or select template
8. CM: Send or schedule campaign
9. 👉 COME BACK TO CDP
10. CDP: Clear campaign tag after sending
```

**Pain Points**:
- Context switching between applications
- Manual segment creation in CM
- Manual campaign setup
- Easy to forget cleanup step

### Desired Workflow (All in CDP)

```
1. CDP: Tag users with segment builder ✅
2. CDP: Create segment in CM via API (NEW)
3. CDP: Select email template from CM (NEW)
4. CDP: Configure campaign settings (NEW)
5. CDP: Send test emails (optional) (NEW)
6. CDP: Send or schedule campaign (NEW)
7. CDP: Auto-track campaign stats (NEW)
8. CDP: Auto-cleanup tags after send (NEW)
```

**Benefits**:
- Never leave CDP interface
- Automatic segment creation
- Automated cleanup
- Campaign stats auto-imported
- Consistent UX

---

## Technical Requirements

### Campaign Monitor SDK Capabilities

Your application already has `campaignmonitor/createsend-php` v7.1 installed with **full campaign creation and sending support**.

#### Available SDK Classes

```php
use CS_REST_Campaigns;      // Create and send campaigns
use CS_REST_Segments;        // Create segments (already used)
use CS_REST_Templates;       // List available templates
use CS_REST_Clients;         // List campaigns, templates, etc
```

#### Key SDK Methods Needed

**1. List Templates** (for user selection):
```php
$auth = ['api_key' => config('campaign-monitor.api_key')];
$wrap = new CS_REST_Clients($clientId, $auth);
$result = $wrap->get_templates();

// Returns array of templates:
// [
//   {
//     "TemplateID": "abc123",
//     "Name": "Monthly Newsletter",
//     "PreviewURL": "https://...",
//     "ScreenshotURL": "https://..."
//   }
// ]
```

**2. Create Campaign from Template**:
```php
$auth = ['api_key' => config('campaign-monitor.api_key')];
$wrap = new CS_REST_Campaigns(null, $auth);

$result = $wrap->create_from_template($clientId, [
    'Subject' => 'November Newsletter',
    'Name' => 'Newsletter - Nov 2024',
    'FromName' => 'Your Company',
    'FromEmail' => 'hello@yourcompany.com',
    'ReplyTo' => 'support@yourcompany.com',
    'TemplateID' => 'abc123',
    'TemplateContent' => [
        // Dynamic content for template placeholders
        'Singlelines' => [
            ['Content' => 'Special offer text', 'Href' => 'https://...']
        ],
        'Multilines' => [
            ['Content' => '<p>Email body content</p>']
        ],
        'Images' => [
            ['Content' => 'https://your-cdn.com/image.jpg', 'Href' => 'https://...', 'Alt' => 'Alt text']
        ],
        'Repeaters' => [
            // For repeating sections like product lists
        ]
    ],
    'SegmentIDs' => [$segmentId], // Use segment created from temp_campaign_tag
]);

// Returns: ['campaign_id' => 'campaign123']
```

**3. Send Test Emails**:
```php
$campaigns = new CS_REST_Campaigns($campaignId, $auth);
$result = $campaigns->send_preview(
    ['test@example.com', 'admin@example.com'],
    'Random' // Personalization: 'Random', 'FirstOnList', or 'SpecificSubscriber'
);
```

**4. Send Campaign Immediately**:
```php
$campaigns = new CS_REST_Campaigns($campaignId, $auth);
$result = $campaigns->send([
    'ConfirmationEmail' => 'admin@yourcompany.com',
    'SendDate' => 'immediately'
]);
```

**5. Schedule Campaign for Later**:
```php
$campaigns = new CS_REST_Campaigns($campaignId, $auth);
$result = $campaigns->send([
    'ConfirmationEmail' => 'admin@yourcompany.com',
    'SendDate' => '2024-12-25 09:00' // YYYY-MM-DD HH:MM in list timezone
]);
```

---

## Implementation Plan

### Phase 1: Service Layer (Backend)

**File**: `app/Services/CmCampaignService.php` (new)

```php
<?php

namespace App\Services;

use CS_REST_Campaigns;
use CS_REST_Clients;
use CS_REST_Templates;
use Illuminate\Support\Facades\Log;

class CmCampaignService
{
    protected string $apiKey;
    protected string $clientId;

    public function __construct()
    {
        $this->apiKey = config('campaign-monitor.api_key');
        $this->clientId = config('campaign-monitor.client_id');
    }

    /**
     * List available templates from Campaign Monitor
     */
    public function listTemplates(): array
    {
        $auth = ['api_key' => $this->apiKey];
        $wrap = new CS_REST_Clients($this->clientId, $auth);
        $result = $wrap->get_templates();

        if (!$result->was_successful()) {
            throw new \Exception('Failed to fetch templates: ' . json_encode($result->response));
        }

        return $result->response;
    }

    /**
     * Create campaign from Campaign Monitor template
     */
    public function createCampaignFromTemplate(array $campaignData): string
    {
        $auth = ['api_key' => $this->apiKey];
        $wrap = new CS_REST_Campaigns(null, $auth);

        $result = $wrap->create_from_template($this->clientId, $campaignData);

        if (!$result->was_successful()) {
            Log::error('Failed to create campaign', [
                'response' => $result->response,
                'http_code' => $result->http_status_code,
            ]);
            throw new \Exception('Failed to create campaign: ' . json_encode($result->response));
        }

        return $result->response; // Returns campaign_id
    }

    /**
     * Send test preview emails
     */
    public function sendPreview(string $campaignId, array $recipients, string $personalize = 'Random'): bool
    {
        $auth = ['api_key' => $this->apiKey];
        $campaigns = new CS_REST_Campaigns($campaignId, $auth);

        $result = $campaigns->send_preview($recipients, $personalize);

        if (!$result->was_successful()) {
            Log::error('Failed to send preview', [
                'campaign_id' => $campaignId,
                'response' => $result->response,
            ]);
            throw new \Exception('Failed to send preview: ' . json_encode($result->response));
        }

        return true;
    }

    /**
     * Send or schedule campaign
     */
    public function sendCampaign(string $campaignId, string $confirmationEmail, string $sendDate = 'immediately'): bool
    {
        $auth = ['api_key' => $this->apiKey];
        $campaigns = new CS_REST_Campaigns($campaignId, $auth);

        $result = $campaigns->send([
            'ConfirmationEmail' => $confirmationEmail,
            'SendDate' => $sendDate,
        ]);

        if (!$result->was_successful()) {
            Log::error('Failed to send campaign', [
                'campaign_id' => $campaignId,
                'response' => $result->response,
            ]);
            throw new \Exception('Failed to send campaign: ' . json_encode($result->response));
        }

        return true;
    }
}
```

### Phase 2: Database Schema

**Migration**: Add campaign drafts tracking

```php
// database/migrations/YYYY_MM_DD_HHMMSS_add_draft_campaigns_tracking.php

Schema::table('cm_campaigns', function (Blueprint $table) {
    $table->string('draft_status')->default('sent')->after('status')
        ->comment('draft, preview_sent, scheduled, sent');
    $table->string('template_id')->nullable()->after('cm_campaign_id')
        ->comment('CM template ID used');
    $table->json('template_content')->nullable()->after('template_id')
        ->comment('Template content data');
    $table->string('scheduled_for')->nullable()->after('sent_at')
        ->comment('Scheduled send date/time');
});
```

### Phase 3: UI Enhancement

**Add to**: `app/Livewire/Admin/Campaigns/CampaignManager.php`

```php
// Add properties
public $showCampaignBuilder = false;
public $selectedTemplate = null;
public $availableTemplates = [];
public $campaignSubject = '';
public $campaignFromName = '';
public $campaignFromEmail = '';
public $campaignReplyTo = '';
public $sendDate = 'immediately';
public $testEmails = '';
public $confirmationEmail = '';

// Add methods
public function openCampaignBuilder($campaignTag)
{
    $this->selectedCampaign = $campaignTag;
    $this->loadAvailableTemplates();
    $this->showCampaignBuilder = true;
}

public function loadAvailableTemplates()
{
    $service = app(CmCampaignService::class);
    $this->availableTemplates = $service->listTemplates();
}

public function sendTestEmails()
{
    $emails = array_map('trim', explode(',', $this->testEmails));

    // Validate emails
    foreach ($emails as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            session()->flash('error', "Invalid email: {$email}");
            return;
        }
    }

    try {
        $service = app(CmCampaignService::class);

        // 1. Create segment from tag
        $segmentId = app(CmSyncService::class)->createCampaignTagSegment($this->selectedCampaign);

        // 2. Create campaign
        $campaignId = $service->createCampaignFromTemplate([
            'Subject' => $this->campaignSubject,
            'Name' => $this->selectedCampaign,
            'FromName' => $this->campaignFromName,
            'FromEmail' => $this->campaignFromEmail,
            'ReplyTo' => $this->campaignReplyTo,
            'TemplateID' => $this->selectedTemplate,
            'TemplateContent' => [], // Or populate from form
            'SegmentIDs' => [$segmentId],
        ]);

        // 3. Send preview
        $service->sendPreview($campaignId, $emails);

        session()->flash('success', 'Test emails sent to: ' . implode(', ', $emails));
    } catch (\Exception $e) {
        session()->flash('error', 'Failed to send test emails: ' . $e->getMessage());
    }
}

public function sendCampaignNow()
{
    try {
        $service = app(CmCampaignService::class);
        $syncService = app(CmSyncService::class);

        // 1. Create segment
        $segmentId = $syncService->createCampaignTagSegment($this->selectedCampaign);

        // 2. Create campaign
        $campaignId = $service->createCampaignFromTemplate([
            'Subject' => $this->campaignSubject,
            'Name' => $this->selectedCampaign,
            'FromName' => $this->campaignFromName,
            'FromEmail' => $this->campaignFromEmail,
            'ReplyTo' => $this->campaignReplyTo,
            'TemplateID' => $this->selectedTemplate,
            'TemplateContent' => [],
            'SegmentIDs' => [$segmentId],
        ]);

        // 3. Send campaign
        $service->sendCampaign(
            $campaignId,
            $this->confirmationEmail ?: auth()->user()->email,
            $this->sendDate
        );

        // 4. Track locally
        CmCampaign::create([
            'cm_campaign_id' => $campaignId,
            'name' => $this->selectedCampaign,
            'subject' => $this->campaignSubject,
            'from_name' => $this->campaignFromName,
            'from_email' => $this->campaignFromEmail,
            'reply_to' => $this->campaignReplyTo,
            'template_id' => $this->selectedTemplate,
            'status' => $this->sendDate === 'immediately' ? 'sent' : 'scheduled',
            'sent_at' => $this->sendDate === 'immediately' ? now() : $this->sendDate,
        ]);

        // 5. Schedule cleanup
        dispatch(new ClearTagBulkJob($this->selectedCampaign))->delay(now()->addHours(2));

        session()->flash('success', 'Campaign sent successfully!');
        $this->showCampaignBuilder = false;
        $this->loadActiveCampaigns();

    } catch (\Exception $e) {
        session()->flash('error', 'Failed to send campaign: ' . $e->getMessage());
    }
}
```

### Phase 4: UI Template (Blade)

**Add to**: `resources/views/livewire/admin/campaigns/campaign-manager.blade.php`

```blade
<!-- Campaign Builder Modal -->
@if($showCampaignBuilder)
    <flux:modal wire:model="showCampaignBuilder" class="max-w-4xl">
        <flux:heading>Send Campaign: {{ $selectedCampaign }}</flux:heading>
        <flux:subheading>Create and send email campaign from Campaign Monitor template</flux:subheading>

        <form wire:submit="sendCampaignNow" class="space-y-6 mt-6">
            <!-- Template Selection -->
            <flux:select wire:model.live="selectedTemplate" label="Email Template" required>
                <option value="">Select a template from Campaign Monitor</option>
                @foreach($availableTemplates as $template)
                    <option value="{{ $template->TemplateID }}">{{ $template->Name }}</option>
                @endforeach
            </flux:select>

            <!-- Template Preview -->
            @if($selectedTemplate)
                @php
                    $template = collect($availableTemplates)->firstWhere('TemplateID', $selectedTemplate);
                @endphp
                @if($template && $template->PreviewURL)
                    <div class="border rounded-lg p-4">
                        <p class="text-sm font-semibold mb-2">Template Preview:</p>
                        <a href="{{ $template->PreviewURL }}" target="_blank" class="text-blue-600 hover:underline text-sm">
                            View full preview →
                        </a>
                    </div>
                @endif
            @endif

            <!-- Campaign Details -->
            <flux:input wire:model="campaignSubject" label="Email Subject Line" required />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="campaignFromName" label="From Name" required />
                <flux:input wire:model="campaignFromEmail" type="email" label="From Email" required />
            </div>

            <flux:input wire:model="campaignReplyTo" type="email" label="Reply-To Email" required />

            <!-- Send Options -->
            <flux:select wire:model.live="sendDate" label="When to Send">
                <option value="immediately">Send Immediately</option>
                <option value="scheduled">Schedule for Later</option>
            </flux:select>

            @if($sendDate === 'scheduled')
                <flux:input
                    wire:model="sendDate"
                    type="datetime-local"
                    label="Schedule Date & Time"
                    description="Timezone: Campaign Monitor list timezone"
                    required
                />
            @endif

            <flux:input
                wire:model="confirmationEmail"
                type="email"
                label="Confirmation Email (optional)"
                description="Where to send campaign confirmation (defaults to your email)"
            />

            <!-- Test Emails -->
            <div class="border-t pt-4">
                <flux:input
                    wire:model="testEmails"
                    label="Send Test Emails (Optional)"
                    placeholder="admin@example.com, test@example.com"
                    description="Comma-separated email addresses to send test campaign"
                />
                <flux:button
                    wire:click.prevent="sendTestEmails"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="mt-2"
                    :disabled="!$testEmails || !$selectedTemplate"
                >
                    Send Test Emails
                </flux:button>
            </div>

            <!-- Actions -->
            <div class="flex justify-between items-center pt-4 border-t">
                <flux:button type="button" wire:click="$set('showCampaignBuilder', false)" variant="ghost">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary" :disabled="!$selectedTemplate">
                    {{ $sendDate === 'immediately' ? 'Send Campaign Now' : 'Schedule Campaign' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
@endif
```

**Add button to Active Tags list**:

```blade
<!-- In the active campaigns foreach loop, add a send button -->
<div class="flex gap-2">
    <flux:button
        wire:click="openCampaignBuilder('{{ $tag }}')"
        size="sm"
        variant="primary"
        icon="paper-airplane"
    >
        Send Campaign
    </flux:button>

    <flux:button wire:click="viewCampaignStats('{{ $tag }}')" size="sm" variant="outline">
        Details
    </flux:button>

    <flux:button
        wire:click="clearCampaign('{{ $tag }}')"
        wire:confirm="Are you sure you want to clear this tag from all users?"
        size="sm"
        variant="ghost"
    >
        Clear
    </flux:button>
</div>
```

---

## Complete Workflow Example

### User Story: Marketing Manager Sends Newsletter

**Step 1**: Tag Users in CDP
```
1. Go to Campaign Manager → Tag Users (CDP)
2. Set filters:
   - Tier: Paid Premium
   - Min Engagement: 50%
   - CM Status: Active
3. Click "Preview Matches" → Shows 2,847 users
4. Click "Tag Users in CDP Database"
5. Success: 2,847 users tagged with "campaign_premium_newsletter_20241211"
```

**Step 2**: Send Campaign from CDP (NEW)
```
6. Go to "Active Tags (CDP)" tab
7. Find "campaign_premium_newsletter_20241211"
8. Click "Send Campaign" button → Modal opens
9. Select template: "Monthly Newsletter Template"
10. Fill in:
    - Subject: "December Updates - Premium Features"
    - From Name: "YourApp Team"
    - From Email: "hello@yourapp.com"
    - Reply-To: "support@yourapp.com"
    - Send: "Immediately"
11. (Optional) Enter test email: "marketing@yourapp.com"
12. (Optional) Click "Send Test Emails" → Receives test
13. Click "Send Campaign Now"
14. Success! Campaign sent to 2,847 users
15. Tag automatically cleared after 2 hours
16. Campaign stats auto-imported to "CM Campaigns" tab
```

**No Campaign Monitor Login Required!**

---

## Auto-Import Campaign Stats

After sending, automatically import the campaign back to your CDP:

```php
// In sendCampaignNow() method, after sending:

// Schedule stats import for 1 hour later (give time for opens/clicks)
dispatch(function() use ($campaignId) {
    $statsService = app(CmCampaignStatsService::class);
    $statsService->importCampaign((object)['CampaignID' => $campaignId]);
})->delay(now()->addHour());
```

This will automatically:
1. Fetch campaign stats from CM API
2. Store in `cm_campaigns` table
3. Make stats visible in "CM Campaigns" tab
4. Track opens, clicks, bounces, etc.

---

## Configuration Requirements

**Environment Variables** (already exist):
```env
CM_API_KEY=your-api-key
CM_CLIENT_ID=your-client-id
CM_LIST_ID=your-list-id
```

**Template Setup in Campaign Monitor**:
1. Create email templates in your CM account
2. Templates must have proper layout/content areas defined
3. Templates will appear in dropdown for selection

---

## Error Handling

### Common Issues

**1. Template Not Found**
```
Error: "Template abc123 does not exist"
Solution: Verify template exists in Campaign Monitor account
```

**2. Invalid Segment**
```
Error: "Segment xyz789 does not exist"
Solution: Ensure segment was created successfully before campaign
```

**3. Scheduled Time in Past**
```
Error: "SendDate must be in the future"
Solution: Validate datetime picker allows future dates only
```

**4. Missing Required Fields**
```
Error: "Subject is required"
Solution: Add validation to form before submission
```

### Logging

All operations log to Laravel logs:

```php
// Success
Log::info('Campaign sent successfully', [
    'campaign_id' => $campaignId,
    'segment_id' => $segmentId,
    'recipients' => $recipientCount,
]);

// Error
Log::error('Failed to send campaign', [
    'campaign_tag' => $tag,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
]);
```

---

## Testing Strategy

### Manual Testing Checklist

- [ ] List templates from CM
- [ ] Select template and preview
- [ ] Fill campaign form with all fields
- [ ] Send test emails
- [ ] Send campaign immediately
- [ ] Schedule campaign for future
- [ ] Verify segment created in CM
- [ ] Verify campaign created in CM
- [ ] Verify campaign sent in CM
- [ ] Verify stats auto-imported to CDP
- [ ] Verify tag auto-cleared after send
- [ ] Test error handling (invalid template, etc.)

### Automated Tests

```php
// tests/Feature/Services/CmCampaignServiceTest.php

test('it lists available templates', function() {
    $service = new CmCampaignService();
    $templates = $service->listTemplates();

    expect($templates)->toBeArray();
    expect($templates[0])->toHaveKeys(['TemplateID', 'Name']);
});

test('it creates campaign from template', function() {
    $service = new CmCampaignService();
    $campaignId = $service->createCampaignFromTemplate([
        'Subject' => 'Test Campaign',
        'Name' => 'Test',
        'FromName' => 'Test',
        'FromEmail' => 'test@example.com',
        'ReplyTo' => 'test@example.com',
        'TemplateID' => 'template123',
        'SegmentIDs' => ['segment123'],
    ]);

    expect($campaignId)->toBeString();
});

test('it sends test preview emails', function() {
    $service = new CmCampaignService();
    $result = $service->sendPreview('campaign123', ['test@example.com']);

    expect($result)->toBeTrue();
});

test('it sends campaign immediately', function() {
    $service = new CmCampaignService();
    $result = $service->sendCampaign('campaign123', 'admin@example.com', 'immediately');

    expect($result)->toBeTrue();
});
```

---

## Performance Considerations

### API Rate Limits

Campaign Monitor API has rate limits:
- **120 requests per minute** per account
- Segment creation: 1 API call
- Campaign creation: 1 API call
- Send campaign: 1 API call
- Total: **3 API calls** per campaign send

### Optimization

**1. Cache Templates**:
```php
public function listTemplates(): array
{
    return Cache::remember('cm_templates', now()->addHours(24), function() {
        // Fetch from CM API
        return $this->fetchTemplates();
    });
}
```

**2. Queue Heavy Operations**:
```php
// Instead of immediate send
dispatch(new SendCampaignJob($campaignData))->onQueue('campaigns');
```

**3. Batch Stats Import**:
```php
// Import stats for multiple campaigns at once
dispatch(new ImportCampaignStatsJob($campaignIds))->delay(now()->addHour());
```

---

## Security Considerations

### Permissions

Add authorization:

```php
// In CampaignManager.php
public function sendCampaignNow()
{
    $this->authorize('send-campaigns'); // Requires permission

    // ... rest of method
}
```

### Validation

Validate all user inputs:

```php
protected function rules()
{
    return [
        'campaignSubject' => 'required|string|max:255',
        'campaignFromName' => 'required|string|max:255',
        'campaignFromEmail' => 'required|email',
        'campaignReplyTo' => 'required|email',
        'selectedTemplate' => 'required|string',
        'testEmails' => 'nullable|string',
        'confirmationEmail' => 'nullable|email',
        'sendDate' => 'required|string',
    ];
}
```

### Audit Logging

Log all campaign sends:

```php
// After successful send
AuditLog::create([
    'user_id' => auth()->id(),
    'action' => 'campaign_sent',
    'details' => [
        'campaign_id' => $campaignId,
        'subject' => $this->campaignSubject,
        'recipients' => $recipientCount,
        'sent_at' => now(),
    ],
]);
```

---

## Benefits Summary

**User Experience**:
- ✅ Never leave CDP interface
- ✅ Streamlined workflow (10 steps → 6 steps)
- ✅ Automatic segment creation
- ✅ Automatic cleanup
- ✅ Automatic stats tracking

**Technical**:
- ✅ Uses existing SDK (no new dependencies)
- ✅ Leverages existing segment workflow
- ✅ Integrates with existing campaign stats system
- ✅ Maintains audit trail
- ✅ Error handling and logging

**Operational**:
- ✅ Reduces human error (no manual CM steps)
- ✅ Faster campaign deployment
- ✅ Better tracking and reporting
- ✅ Consistent process for all users

---

## Estimated Implementation Time

| Task | Hours | Notes |
|------|-------|-------|
| CmCampaignService (4 methods) | 2-3 | Straightforward SDK wrappers |
| Database migration | 0.5 | Add tracking fields |
| UI: Template selection | 2 | Fetch and display templates |
| UI: Campaign form | 2 | Form inputs and validation |
| UI: Send/schedule logic | 2 | Button handlers and API calls |
| UI: Test emails feature | 1 | Preview sending |
| Integration: Segment creation | 1 | Link to existing segment service |
| Integration: Stats import | 1 | Auto-import after send |
| Integration: Auto cleanup | 1 | Schedule cleanup job |
| Testing: Unit tests | 2 | Service layer tests |
| Testing: Feature tests | 2 | End-to-end workflow tests |
| Testing: Manual QA | 2 | Test all user flows |
| Documentation | 1 | Update docs and comments |
| **TOTAL** | **19-20 hours** | ~2.5 days of development |

---

## Next Steps

See `TODO-SEND-CAMPAIGNS-FEATURE.md` for detailed implementation task list.

**Priority Order**:
1. Phase 1: Service Layer (backend API wrappers)
2. Phase 2: Database Schema (tracking fields)
3. Phase 3: UI Enhancement (Livewire component)
4. Phase 4: UI Template (Blade views)
5. Phase 5: Integration (glue it all together)
6. Phase 6: Testing (unit + feature + manual)

---

*Status: Not Yet Implemented*
*Created: November 11, 2024*
*Framework: Laravel 12.35 + Campaign Monitor PHP SDK 7.1*
