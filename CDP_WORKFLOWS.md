# CDP Campaign Workflows - Implementation Guide (UI-First)

**Date Created:** 2025-11-12
**Document Version:** 2.0
**Status:** Ready for Implementation
**Tech Stack:** Laravel 12 + Livewire 3.6 + Flux Pro + Tailwind v4

This document details the three core workflows for the B2B Multi-Org CDP with Campaign Monitor integration, with comprehensive UI/UX specifications for every user-facing action.

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

### UI WORKFLOW: Campaign Template Management

#### Component: CampaignTemplateManager (Livewire)
**Route:** `/admin/cdp/campaign-templates`
**File:** `app/Livewire/Admin/Cdp/CampaignTemplateManager.php`

#### Visual Elements

**Page Header (Flux Components):**
```blade
<flux:header>
    <flux:heading>Recurring Campaign Templates</flux:heading>
    <flux:subheading>Manage automated tier-based email campaigns</flux:subheading>
    <flux:button wire:click="openCreateModal" icon="plus">
        Create Template
    </flux:button>
</flux:header>
```

**Stats Cards (Dark Mode Compatible):**
```blade
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <!-- Active Templates -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-neutral-200 dark:border-neutral-700">
        <div class="text-sm text-gray-600 dark:text-gray-400">Active Templates</div>
        <div class="text-3xl font-bold text-gray-900 dark:text-white mt-2">
            {{ $activeTemplatesCount }}
        </div>
        <div class="text-xs text-green-600 dark:text-green-400 mt-1">
            {{ $scheduledToday }} scheduled today
        </div>
    </div>

    <!-- Campaigns Sent (This Month) -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-neutral-200 dark:border-neutral-700">
        <div class="text-sm text-gray-600 dark:text-gray-400">Sent This Month</div>
        <div class="text-3xl font-bold text-gray-900 dark:text-white mt-2">
            {{ $campaignsSentMonth }}
        </div>
        <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
            {{ $avgRecipientsMonth }} avg recipients
        </div>
    </div>

    <!-- Next Scheduled Send -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-neutral-200 dark:border-neutral-700">
        <div class="text-sm text-gray-600 dark:text-gray-400">Next Send</div>
        <div class="text-xl font-semibold text-gray-900 dark:text-white mt-2">
            {{ $nextScheduledTime }}
        </div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            {{ $nextScheduledTemplate }}
        </div>
    </div>

    <!-- Total Recipients -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-neutral-200 dark:border-neutral-700">
        <div class="text-sm text-gray-600 dark:text-gray-400">Total Recipients</div>
        <div class="text-3xl font-bold text-gray-900 dark:text-white mt-2">
            {{ number_format($totalRecipients) }}
        </div>
        <div class="text-xs text-purple-600 dark:text-purple-400 mt-1">
            across all tiers
        </div>
    </div>
</div>
```

**Template List Table:**
```blade
<div class="bg-white dark:bg-zinc-800 rounded-lg border border-neutral-200 dark:border-neutral-700">
    <table class="w-full">
        <thead class="bg-gray-50 dark:bg-zinc-900 border-b border-neutral-200 dark:border-neutral-700">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Template</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Schedule</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Next Send</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Last Sent</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
            @forelse($templates as $template)
                <tr class="hover:bg-gray-50 dark:hover:bg-zinc-900/50" wire:key="template-{{ $template->id }}">
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $template->name }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $template->subject }}
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                            {{ $template->type === 'daily_newsletter' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : '' }}
                            {{ $template->type === 'weekly_digest' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : '' }}
                            {{ $template->type === 'monthly_report' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300' : '' }}">
                            {{ ucwords(str_replace('_', ' ', $template->type)) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                        {{ $template->schedule_display }}
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 dark:text-white">
                            {{ $template->next_send_at?->format('M d, Y') }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $template->next_send_at?->format('h:i A') }}
                        </div>
                        <div class="text-xs text-blue-600 dark:text-blue-400 mt-1" wire:poll.30s>
                            {{ $template->time_until_send }}
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                        {{ $template->last_sent_at?->diffForHumans() ?? 'Never' }}
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                            {{ $template->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300' }}">
                            {{ $template->is_active ? 'Active' : 'Paused' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <flux:button size="sm" variant="ghost" wire:click="editTemplate({{ $template->id }})">Edit</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="toggleStatus({{ $template->id }})">
                            {{ $template->is_active ? 'Pause' : 'Resume' }}
                        </flux:button>
                        <flux:button size="sm" variant="primary" wire:click="sendNow({{ $template->id }})">Send Now</flux:button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center">
                        <div class="text-gray-400 dark:text-gray-500">
                            <svg class="mx-auto h-12 w-12 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-sm font-medium">No campaign templates yet</p>
                            <p class="text-xs mt-1">Create your first recurring campaign template to get started</p>
                            <flux:button class="mt-4" wire:click="openCreateModal">Create Template</flux:button>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
```

**Create/Edit Template Modal (Custom Tailwind, not Flux modal):**
```blade
@if($showTemplateModal)
<div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-500 dark:bg-black bg-opacity-75 dark:bg-opacity-50 transition-opacity" wire:click="closeModal"></div>

        <!-- Modal -->
        <div class="inline-block align-bottom bg-white dark:bg-zinc-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-neutral-200 dark:border-neutral-700">
            <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    {{ $editingTemplate ? 'Edit Template' : 'Create Recurring Campaign Template' }}
                </h3>
            </div>

            <div class="px-6 py-4 space-y-4">
                <!-- Template Name -->
                <div>
                    <flux:input
                        wire:model.live.debounce.500ms="form.name"
                        label="Template Name"
                        placeholder="e.g., Daily Pro Newsletter"
                        required />
                    @error('form.name') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Type Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Campaign Type</label>
                    <div class="grid grid-cols-3 gap-3">
                        <button type="button" wire:click="$set('form.type', 'daily_newsletter')"
                            class="p-4 border-2 rounded-lg transition
                            {{ $form['type'] === 'daily_newsletter' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-neutral-200 dark:border-neutral-700 hover:border-blue-300' }}">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">Daily</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Newsletter</div>
                        </button>
                        <button type="button" wire:click="$set('form.type', 'weekly_digest')"
                            class="p-4 border-2 rounded-lg transition
                            {{ $form['type'] === 'weekly_digest' ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-neutral-200 dark:border-neutral-700 hover:border-green-300' }}">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">Weekly</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Digest</div>
                        </button>
                        <button type="button" wire:click="$set('form.type', 'monthly_report')"
                            class="p-4 border-2 rounded-lg transition
                            {{ $form['type'] === 'monthly_report' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20' : 'border-neutral-200 dark:border-neutral-700 hover:border-purple-300' }}">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">Monthly</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Report</div>
                        </button>
                    </div>
                </div>

                <!-- Schedule Configuration -->
                <div class="grid grid-cols-2 gap-4">
                    @if($form['type'] === 'daily_newsletter')
                        <flux:input type="time" wire:model="form.send_time" label="Send Time" required />
                    @elseif($form['type'] === 'weekly_digest')
                        <flux:select wire:model="form.day_of_week" label="Day of Week" required>
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                        </flux:select>
                        <flux:input type="time" wire:model="form.send_time" label="Send Time" required />
                    @elseif($form['type'] === 'monthly_report')
                        <flux:input type="number" wire:model="form.day_of_month" label="Day of Month" min="1" max="28" required />
                        <flux:input type="time" wire:model="form.send_time" label="Send Time" required />
                    @endif
                </div>

                <!-- Target Tiers -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Target Tiers</label>
                    <div class="space-y-2">
                        @foreach($tiers as $tier)
                            <flux:checkbox wire:model="form.tier_ids" value="{{ $tier->id }}">
                                <span class="inline-flex items-center">
                                    {{ $tier->name }}
                                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                        ({{ $tier->organizations_count }} orgs)
                                    </span>
                                </span>
                            </flux:checkbox>
                        @endforeach
                    </div>
                </div>

                <!-- Subject Line -->
                <flux:input wire:model="form.subject" label="Email Subject" placeholder="Will append date automatically" required />

                <!-- Campaign Monitor Template ID -->
                <flux:input wire:model="form.cm_template_id" label="Campaign Monitor Template ID" required />

                <!-- Preview Next Sends -->
                @if($form['type'] && $form['send_time'])
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-blue-900 dark:text-blue-300 mb-2">Next 5 Scheduled Sends:</h4>
                        <ul class="text-xs text-blue-700 dark:text-blue-400 space-y-1">
                            @foreach($this->previewSchedule() as $date)
                                <li>• {{ $date }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="px-6 py-4 bg-gray-50 dark:bg-zinc-900 border-t border-neutral-200 dark:border-neutral-700 flex justify-end space-x-3">
                <flux:button variant="ghost" wire:click="closeModal">Cancel</flux:button>
                <flux:button variant="primary" wire:click="saveTemplate" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveTemplate">{{ $editingTemplate ? 'Update' : 'Create' }} Template</span>
                    <span wire:loading wire:target="saveTemplate">Saving...</span>
                </flux:button>
            </div>
        </div>
    </div>
</div>
@endif
```

#### Real-Time Features

1. **Live Countdown Timer:** `wire:poll.30s` updates "time until send"
2. **Stats Auto-Refresh:** `wire:poll.60s` on stats cards
3. **Instant Pause/Resume:** Toggle status without page reload
4. **Send Now Action:** Immediate campaign dispatch with confirmation modal

#### User Actions Flow

1. **Create Template:**
   - Click "Create Template" → Modal opens
   - Select type → Schedule fields appear
   - Select tiers → See preview of next 5 sends
   - Enter CM template ID → Save
   - Flash success message → Template appears in list

2. **Edit Template:**
   - Click "Edit" → Modal opens with pre-filled data
   - Modify fields → See updated schedule preview
   - Save → List updates instantly

3. **Pause/Resume:**
   - Click "Pause" → Status changes to yellow "Paused"
   - Scheduler skips this template
   - Click "Resume" → Status returns to green "Active"

4. **Send Now (Manual Trigger):**
   - Click "Send Now" → Confirmation modal
   - "Send to 2,345 Pro/Enterprise users now?"
   - Confirm → Job dispatched → Success toast → Campaign tracked

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

---

## Product Subscription Filtering Patterns

### Overview

Product opt-in/opt-out filtering is a **CRITICAL** segmentation capability that requires the **DYNAMIC TAG** approach due to the JOIN complexity with `user_product_subscription` table. This section documents all product filtering patterns supported by the SegmentQueryBuilder.

### Why Dynamic Tags for Product Filtering?

```
Product Subscription Query Characteristics:
├── Requires JOIN with user_product_subscription table
├── Multiple conditions (product_id, is_active, subscribed_at, unsubscribed_at)
├── Support for multi-product filtering (OR/AND logic)
├── Exclusion queries (NOT subscribed)
├── Historical data (opted-out users for win-back campaigns)
└── Campaign Monitor CANNOT replicate this logic → MUST use Dynamic Tags
```

### Supported Filter Types

The SegmentQueryBuilder supports **4 product filter types**:

| Filter Type | Description | Use Case | Dynamic Tag Required |
|-------------|-------------|----------|---------------------|
| `subscribed_to_product` | Active subscribers to specific product(s) | Standard product targeting | ✅ Yes |
| `not_subscribed_to_product` | Users NOT subscribed to specific product(s) | Exclusion campaigns | ✅ Yes |
| `opted_out_of_product` | Users who unsubscribed from product(s) | Win-back campaigns | ✅ Yes |
| `active_products_count` | Users with X active subscriptions | Engagement threshold | ✅ Yes |

### Implementation: SegmentQueryBuilder Service

```php
// app/Services/SegmentQueryBuilder.php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

class SegmentQueryBuilder
{
    public function build(array $rules): Builder
    {
        $query = User::query();

        foreach ($rules as $rule) {
            $query = match($rule['field']) {
                'tier' => $this->addTierFilter($query, $rule),
                'subscribed_to_product' => $this->filterBySubscribedProduct($query, $rule['operator'], $rule['value']),
                'not_subscribed_to_product' => $this->filterByNotSubscribedProduct($query, $rule['operator'], $rule['value']),
                'opted_out_of_product' => $this->filterByOptedOut($query, $rule['operator'], $rule['value']),
                'active_products_count' => $this->filterByProductCount($query, $rule['operator'], $rule['value']),
                'activity_score_7d' => $this->addActivityFilter($query, $rule),
                'event_attendance' => $this->addEventFilter($query, $rule),
                default => $query,
            };
        }

        return $query;
    }

    /**
     * Filter users subscribed to specific product(s)
     */
    protected function filterBySubscribedProduct(Builder $query, string $operator, $value): Builder
    {
        if ($operator === 'equals') {
            return $query->whereHas('productSubscriptions', fn($q) =>
                $q->where('product_id', $value)
                  ->where('is_active', true)
            );
        }

        if ($operator === 'in') {
            return $query->whereHas('productSubscriptions', fn($q) =>
                $q->whereIn('product_id', $value)
                  ->where('is_active', true)
            );
        }

        return $query;
    }

    /**
     * Filter users NOT subscribed to specific product(s)
     */
    protected function filterByNotSubscribedProduct(Builder $query, string $operator, $value): Builder
    {
        if ($operator === 'equals') {
            return $query->whereDoesntHave('productSubscriptions', fn($q) =>
                $q->where('product_id', $value)
                  ->where('is_active', true)
            );
        }

        if ($operator === 'in') {
            // Users not subscribed to ANY of the specified products
            return $query->whereDoesntHave('productSubscriptions', fn($q) =>
                $q->whereIn('product_id', $value)
                  ->where('is_active', true)
            );
        }

        return $query;
    }

    /**
     * Filter users who opted out of specific product(s)
     */
    protected function filterByOptedOut(Builder $query, string $operator, $value): Builder
    {
        if ($operator === 'equals') {
            return $query->whereHas('productSubscriptions', fn($q) =>
                $q->where('product_id', $value)
                  ->where('is_active', false)
                  ->whereNotNull('unsubscribed_at')
            );
        }

        if ($operator === 'in') {
            return $query->whereHas('productSubscriptions', fn($q) =>
                $q->whereIn('product_id', $value)
                  ->where('is_active', false)
                  ->whereNotNull('unsubscribed_at')
            );
        }

        return $query;
    }

    /**
     * Filter users by active product subscription count
     */
    protected function filterByProductCount(Builder $query, string $operator, int $value): Builder
    {
        return $query->has('productSubscriptions', $operator, $value);
    }
}
```

### 8 Common Product Filtering Patterns

#### Pattern 1: Single Product Targeting

**Use Case:** Send campaign to all "Premium Newsletter" subscribers

```php
$rules = [
    [
        'field' => 'subscribed_to_product',
        'operator' => 'equals',
        'value' => 5 // Product ID for "Premium Newsletter"
    ]
];

// Generated Query:
User::whereHas('productSubscriptions', fn($q) =>
    $q->where('product_id', 5)
      ->where('is_active', true)
)->get();
```

**Segment Type:** Dynamic Tag
**Estimated Query Time:** 0.2-0.5s for 60K users (with composite index)

---

#### Pattern 2: Multi-Product OR Logic

**Use Case:** Send campaign to users subscribed to "Webinar Access" OR "Premium Newsletter" OR "VIP Events"

```php
$rules = [
    [
        'field' => 'subscribed_to_product',
        'operator' => 'in',
        'value' => [5, 8, 12] // Multiple product IDs
    ]
];

// Generated Query:
User::whereHas('productSubscriptions', fn($q) =>
    $q->whereIn('product_id', [5, 8, 12])
      ->where('is_active', true)
)->get();
```

**UI:** Multi-select checkbox list when operator is "In"
**Segment Type:** Dynamic Tag
**Matching Logic:** User matches if subscribed to ANY of the products

---

#### Pattern 3: Multi-Product AND Logic

**Use Case:** Send campaign to users subscribed to BOTH "Webinar Access" AND "Premium Newsletter"

```php
$rules = [
    [
        'field' => 'subscribed_to_product',
        'operator' => 'equals',
        'value' => 5 // Premium Newsletter
    ],
    [
        'field' => 'subscribed_to_product',
        'operator' => 'equals',
        'value' => 8 // Webinar Access
    ]
];

// Generated Query:
User::whereHas('productSubscriptions', fn($q) =>
        $q->where('product_id', 5)->where('is_active', true)
    )
    ->whereHas('productSubscriptions', fn($q) =>
        $q->where('product_id', 8)->where('is_active', true)
    )
    ->get();
```

**Segment Type:** Dynamic Tag
**Matching Logic:** User must be subscribed to ALL specified products

---

#### Pattern 4: Product Exclusion (NOT Subscribed)

**Use Case:** Send upsell campaign to users NOT subscribed to "Premium Newsletter"

```php
$rules = [
    [
        'field' => 'tier',
        'operator' => 'in',
        'value' => ['pro', 'enterprise']
    ],
    [
        'field' => 'not_subscribed_to_product',
        'operator' => 'equals',
        'value' => 5 // Premium Newsletter
    ]
];

// Generated Query:
User::whereHas('organization.tier', fn($q) =>
        $q->whereIn('name', ['pro', 'enterprise'])
    )
    ->whereDoesntHave('productSubscriptions', fn($q) =>
        $q->where('product_id', 5)->where('is_active', true)
    )
    ->get();
```

**Use Case:** Upsell campaigns for paid users who haven't opted into premium features
**Segment Type:** Dynamic Tag

---

#### Pattern 5: Win-Back Campaign (Opted-Out Users)

**Use Case:** Re-engage users who unsubscribed from "Webinar Access" in last 90 days

```php
$rules = [
    [
        'field' => 'opted_out_of_product',
        'operator' => 'equals',
        'value' => 8 // Webinar Access
    ],
    [
        'field' => 'unsubscribed_within_days',
        'operator' => 'less_than',
        'value' => 90
    ]
];

// Generated Query:
User::whereHas('productSubscriptions', fn($q) =>
        $q->where('product_id', 8)
          ->where('is_active', false)
          ->whereNotNull('unsubscribed_at')
          ->where('unsubscribed_at', '>', now()->subDays(90))
    )
    ->get();
```

**UI Badge:** Show "Opted Out" badge with unsubscribed date
**Campaign Message:** "We noticed you unsubscribed from Webinar Access. Here's what's new..."
**Segment Type:** Dynamic Tag

---

#### Pattern 6: Product Count Threshold

**Use Case:** Send "Power User" campaign to users with 3+ active product subscriptions

```php
$rules = [
    [
        'field' => 'active_products_count',
        'operator' => 'greater_than_or_equal',
        'value' => 3
    ]
];

// Generated Query:
User::has('productSubscriptions', '>=', 3)->get();
```

**Use Case:** Gamification, VIP recognition, exclusive offers
**Segment Type:** Dynamic Tag

---

#### Pattern 7: Tier + Product Combination

**Use Case:** Send "Pro Feature Spotlight" to Pro users who haven't opted into any premium products

```php
$rules = [
    [
        'field' => 'tier',
        'operator' => 'equals',
        'value' => 'pro'
    ],
    [
        'field' => 'active_products_count',
        'operator' => 'equals',
        'value' => 0
    ]
];

// Generated Query:
User::whereHas('organization.tier', fn($q) =>
        $q->where('name', 'pro')
    )
    ->has('productSubscriptions', '=', 0)
    ->get();
```

**Conversion Goal:** Increase product adoption among paid users
**Segment Type:** Dynamic Tag

---

#### Pattern 8: Timeline-Based Product Filtering

**Use Case:** Send "New Subscriber Welcome" to users who subscribed in last 7 days

```php
$rules = [
    [
        'field' => 'subscribed_to_product',
        'operator' => 'equals',
        'value' => 5 // Premium Newsletter
    ],
    [
        'field' => 'subscription_created_within_days',
        'operator' => 'less_than_or_equal',
        'value' => 7
    ]
];

// Generated Query:
User::whereHas('productSubscriptions', fn($q) =>
        $q->where('product_id', 5)
          ->where('is_active', true)
          ->where('subscribed_at', '>', now()->subDays(7))
    )
    ->get();
```

**Segment Type:** Dynamic Tag
**Campaign Type:** Automated welcome series (could be recurring daily job)

---

### Performance Comparison: Persistent vs Dynamic

| Segment Complexity | Persistent Segment | Dynamic Tag | Recommended |
|-------------------|-------------------|-------------|-------------|
| Single product filter | ❌ Not possible | ✅ 0.2-0.5s | Dynamic Tag |
| Multi-product OR | ❌ Not possible | ✅ 0.3-0.7s | Dynamic Tag |
| Multi-product AND | ❌ Not possible | ✅ 0.5-1.2s | Dynamic Tag |
| Product + Tier | ❌ Not possible | ✅ 0.4-0.9s | Dynamic Tag |
| Product + Activity | ❌ Not possible | ✅ 0.6-1.5s | Dynamic Tag |

**Conclusion:** ALL product subscription filtering MUST use Dynamic Tag approach.

### Database Indexes Required

```php
// database/migrations/YYYY_MM_DD_add_product_subscription_indexes.php

Schema::table('user_product_subscription', function (Blueprint $table) {
    // Composite index for active subscription queries
    $table->index(['user_id', 'is_active'], 'idx_user_active');
    $table->index(['product_id', 'is_active'], 'idx_product_active');
    $table->index(['user_id', 'product_id', 'is_active'], 'idx_user_product_active');

    // Index for opted-out user queries (win-back campaigns)
    $table->index('unsubscribed_at', 'idx_unsubscribed_at');

    // Index for timeline-based queries
    $table->index('subscribed_at', 'idx_subscribed_at');
});
```

**Performance Impact:**
- Without indexes: 5-10s query time for 60K users
- With indexes: 0.2-1.5s query time for 60K users
- **Indexes are MANDATORY for production**

### UI Workflow: Product Filtering in Segment Builder

#### Field Selection Dropdown

```blade
<optgroup label="Products">
    <option value="subscribed_to_product">Subscribed to Product</option>
    <option value="not_subscribed_to_product">NOT Subscribed to Product</option>
    <option value="opted_out_of_product">Opted Out of Product</option>
    <option value="active_products_count">Active Products Count</option>
</optgroup>
```

#### Dynamic Value Input (Multi-Product Selection)

```blade
@if($rule['field'] === 'subscribed_to_product' && $rule['operator'] === 'in')
    <div class="space-y-2">
        @foreach($products as $product)
            <label class="flex items-center">
                <flux:checkbox
                    wire:model.live="rules.{{ $ruleIndex }}.value"
                    value="{{ $product->id }}"
                />
                <span class="ml-2">{{ $product->name }}</span>
            </label>
        @endforeach
    </div>
@endif
```

#### Live Preview with Product Context

```blade
<div class="bg-white dark:bg-zinc-800 rounded-lg p-4">
    <h3 class="font-semibold mb-2">Live Preview</h3>
    <div wire:poll.2s>
        <p class="text-2xl font-bold">{{ number_format($matchingUsersCount) }} users</p>
    </div>

    <div class="mt-4">
        <h4 class="text-sm font-medium mb-2">Sample Users (First 5)</h4>
        @foreach($sampleUsers as $user)
            <div class="flex items-center justify-between py-2 border-b">
                <div>
                    <p class="font-medium">{{ $user->name }}</p>
                    <p class="text-xs text-gray-500">{{ $user->email }}</p>
                </div>
                <div class="text-xs">
                    @foreach($user->productSubscriptions->where('is_active', true) as $sub)
                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">
                            {{ $sub->product->name }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
```

### API Call Impact

```
Scenario: Send campaign to 5,000 users subscribed to "Premium Newsletter"

Without Product Filtering (Persistent Segment):
├── Not possible - CM cannot filter by product subscriptions
└── Would require manual CSV export → import → send

With Product Filtering (Dynamic Tag):
├── Step 1: CDP query execution (0.4s)
├── Step 2: Tag 5,000 users in CM (5 API calls, 5-7s)
├── Step 3: Create CM segment (1 API call, 1s)
├── Step 4: Send campaign (2 API calls, 2s)
├── Step 5: Cleanup tag (5 API calls, 5-7s, delayed 2 hours)
└── Total: 13 API calls, ~15 seconds (+ delayed cleanup)

Monthly Impact (10 product-based campaigns):
└── ~130 API calls/month for product filtering campaigns
```

### Testing Checklist

```php
// tests/Feature/ProductFilteringTest.php

it('filters users subscribed to single product', function () {
    $product = Product::factory()->create();
    $subscribedUser = User::factory()->create();
    $subscribedUser->productSubscriptions()->attach($product, ['is_active' => true]);

    $rules = [['field' => 'subscribed_to_product', 'operator' => 'equals', 'value' => $product->id]];
    $query = app(SegmentQueryBuilder::class)->build($rules);

    expect($query->get())->toContain($subscribedUser);
});

it('filters users subscribed to multiple products (OR logic)', function () {
    // Test implementation
});

it('filters users subscribed to multiple products (AND logic)', function () {
    // Test implementation
});

it('excludes users not subscribed to product', function () {
    // Test implementation
});

it('filters opted-out users for win-back campaigns', function () {
    // Test implementation
});

it('filters users by active product count threshold', function () {
    // Test implementation
});
```

---

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

## Campaign Metrics Synchronization Strategy

### Overview

**Problem**: Campaign Monitor tracks email engagement (opens, clicks, bounces, unsubscribes) but this data lives in CM's system. The CDP needs to import and display these metrics to provide comprehensive campaign analytics in one unified interface.

**Solution**: Hybrid webhook + API polling approach for real-time and reliable metrics import.

### Architecture: Webhook + Polling Hybrid

```
┌──────────────────────────────────────────────────────────────────┐
│           CAMPAIGN METRICS IMPORT ARCHITECTURE                   │
└──────────────────────────────────────────────────────────────────┘

Campaign Monitor                      CDP Backend
─────────────────                     ───────────

[Campaign Sent] ────────┐
                        │
[User Opens Email] ─────┼──► WEBHOOK ──────►  POST /webhooks/cm
                        │    (Real-time)      │
[User Clicks Link] ─────┤                     ├──► Store in
                        │                     │    campaign_metrics
[User Bounces] ─────────┤                     │    table
                        │                     │
[User Unsubscribes] ────┘                     ▼
                                        Update Campaign
                                        aggregates
                                        (opens_count,
                                         clicks_count)

                         ┌────────────────────┐
                         │  HOURLY POLLING    │
                         │  (Backup + Missing)│
                         └──────┬─────────────┘
                                │
CM API ◄────── FetchCampaignMetricsJob
GET /campaigns/{id}/summary   │ (Laravel Scheduler)
                               │ Every hour
Returns:                       │
- Opens count                  │
- Unique opens                 ▼
- Clicks count           Compare with
- Unique clicks          stored metrics
- Bounces                      │
- Unsubscribes                 │
                               ▼
                        Fill gaps +
                        validate totals
```

### Why Hybrid Approach?

| Method | Pros | Cons | Use Case |
|--------|------|------|----------|
| **Webhooks Only** | Real-time, low API usage | Can miss events (network issues), no historical data | Not reliable alone |
| **Polling Only** | Reliable, complete data | High API usage, not real-time | Expensive, slow |
| **Hybrid** | Real-time + reliable, fills gaps | Requires both systems | Best of both worlds ✅ |

---

### Workflow 1: Real-Time Webhook Handler

```
User opens email in Campaign Monitor
    │
    ▼
Campaign Monitor fires webhook
    │
    ▼
POST /webhooks/campaign-monitor
├── Headers:
│   ├── X-CM-Signature: <hmac-sha256>
│   └── Content-Type: application/json
│
├── Payload:
│   {
│     "event": "open",
│     "campaign_id": "abc123",
│     "subscriber_email": "user@example.com",
│     "timestamp": "2025-11-12T14:30:00Z",
│     "ip_address": "203.0.113.42",
│     "user_agent": "Mozilla/5.0..."
│   }
│
└── Verification:
    ├── 1. Verify HMAC signature
    │      hash_hmac('sha256', $payload, config('cm.webhook_secret'))
    │
    ├── 2. Validate campaign_id exists in campaigns table
    │
    ├── 3. Dispatch HandleCampaignMetricWebhook job
    │      Queue: webhooks (high priority)
    │
    └── 4. Return 200 OK immediately (acknowledge receipt)

HandleCampaignMetricWebhook Job:
    │
    ├──► 1. Find or create campaign_metrics record for today
    │        WHERE campaign_id = X AND date = CURDATE()
    │
    ├──► 2. Increment appropriate counter:
    │        - event = "open" → opens++
    │        - event = "click" → clicks++
    │        - event = "bounce" → bounces++
    │        - event = "unsubscribe" → unsubscribes++
    │
    ├──► 3. Track unique events (if subscriber_email provided)
    │        Check if this subscriber already counted today
    │        If new → unique_opens++ or unique_clicks++
    │
    ├──► 4. Update Campaign model aggregates:
    │        Campaign::find($campaignId)->increment('opens_count')
    │
    └──► 5. Log webhook event in webhook_logs table
             For auditing and debugging
```

**Implementation**:

```php
// app/Http/Controllers/Webhooks/CampaignMonitorWebhookController.php

public function handle(Request $request)
{
    // Verify webhook signature
    if (!$this->verifySignature($request)) {
        return response()->json(['error' => 'Invalid signature'], 403);
    }

    // Validate payload
    $validated = $request->validate([
        'event' => 'required|in:open,click,bounce,unsubscribe,subscribe',
        'campaign_id' => 'required|string',
        'subscriber_email' => 'required|email',
        'timestamp' => 'required|date',
    ]);

    // Dispatch job (non-blocking)
    HandleCampaignMetricWebhook::dispatch($validated);

    return response()->json(['status' => 'received'], 200);
}

protected function verifySignature(Request $request): bool
{
    $signature = $request->header('X-CM-Signature');
    $payload = $request->getContent();
    $expected = hash_hmac('sha256', $payload, config('services.cm.webhook_secret'));

    return hash_equals($expected, $signature);
}
```

```php
// app/Jobs/HandleCampaignMetricWebhook.php

public function handle()
{
    $campaign = Campaign::where('cm_campaign_id', $this->data['campaign_id'])->first();

    if (!$campaign) {
        Log::warning('Webhook for unknown campaign', ['cm_campaign_id' => $this->data['campaign_id']]);
        return;
    }

    // Find or create today's metrics record
    $metrics = CampaignMetric::firstOrCreate(
        [
            'campaign_id' => $campaign->id,
            'date' => today(),
        ],
        [
            'opens' => 0,
            'unique_opens' => 0,
            'clicks' => 0,
            'unique_clicks' => 0,
            'bounces' => 0,
            'unsubscribes' => 0,
        ]
    );

    // Increment counters
    match($this->data['event']) {
        'open' => $metrics->increment('opens'),
        'click' => $metrics->increment('clicks'),
        'bounce' => $metrics->increment('bounces'),
        'unsubscribe' => $metrics->increment('unsubscribes'),
        default => null,
    };

    // Update campaign aggregates
    $campaign->refreshMetrics();

    // Log for auditing
    WebhookLog::create([
        'source' => 'campaign_monitor',
        'event' => $this->data['event'],
        'payload' => $this->data,
        'processed_at' => now(),
    ]);
}
```

---

### Workflow 2: Hourly Polling Job

```
Laravel Scheduler (every hour)
    │
    ▼
FetchCampaignMetricsJob dispatched
    │
    ├──► 1. Get campaigns sent in last 24 hours
    │        Campaign::where('sent_at', '>', now()->subHours(24))
    │                 ->where('status', 'sent')
    │                 ->orderBy('sent_at', 'desc')
    │                 ->limit(10)
    │                 ->get()
    │
    ├──► 2. For each campaign:
    │        │
    │        ├──► a. Fetch summary from CM API
    │        │      GET /campaigns/{cm_campaign_id}/summary
    │        │      {
    │        │        "TotalOpens": 1250,
    │        │        "UniqueOpens": 850,
    │        │        "Clicks": 320,
    │        │        "UniqueClicks": 180,
    │        │        "Bounces": 45,
    │        │        "Unsubscribed": 12
    │        │      }
    │        │
    │        ├──► b. Compare with stored metrics total
    │        │      $stored = CampaignMetric::where('campaign_id', $id)->sum('opens')
    │        │      $cmTotal = $apiResponse['TotalOpens']
    │        │
    │        ├──► c. If discrepancy > 5%:
    │        │      ├─ Log warning
    │        │      ├─ Create adjustment record
    │        │      └─ Update campaign aggregates to match CM
    │        │
    │        └──► d. Update Campaign model with authoritative totals
    │               Campaign::find($id)->update([
    │                 'opens_count' => $cmTotal['TotalOpens'],
    │                 'unique_opens_count' => $cmTotal['UniqueOpens'],
    │                 'clicks_count' => $cmTotal['Clicks'],
    │                 'unique_clicks_count' => $cmTotal['UniqueClicks'],
    │                 'bounce_count' => $cmTotal['Bounces'],
    │                 'unsubscribe_count' => $cmTotal['Unsubscribed'],
    │                 'last_metrics_sync' => now(),
    │               ])
    │
    ├──► 3. Rate limiting:
    │        Sleep 0.5s between API calls (max 120 calls/hour)
    │
    └──► 4. Log sync summary:
             "Synced metrics for 10 campaigns, 2 discrepancies found"
```

**Implementation**:

```php
// app/Jobs/FetchCampaignMetricsJob.php

public function handle(CampaignMetricsService $service)
{
    $campaigns = Campaign::where('sent_at', '>', now()->subHours(24))
                         ->where('status', 'sent')
                         ->orderBy('sent_at', 'desc')
                         ->limit(10)
                         ->get();

    $synced = 0;
    $discrepancies = 0;

    foreach ($campaigns as $campaign) {
        try {
            // Fetch from CM API
            $cmMetrics = $service->fetchMetricsFromCM($campaign->cm_campaign_id);

            // Compare and update
            $stored = $campaign->opens_count ?? 0;
            $diff = abs($stored - $cmMetrics['TotalOpens']);
            $discrepancy = $stored > 0 ? ($diff / $stored) * 100 : 0;

            if ($discrepancy > 5) {
                Log::warning('Metrics discrepancy detected', [
                    'campaign_id' => $campaign->id,
                    'stored_opens' => $stored,
                    'cm_opens' => $cmMetrics['TotalOpens'],
                    'diff_percent' => $discrepancy,
                ]);
                $discrepancies++;
            }

            // Update with authoritative CM data
            $campaign->update([
                'opens_count' => $cmMetrics['TotalOpens'],
                'unique_opens_count' => $cmMetrics['UniqueOpens'],
                'clicks_count' => $cmMetrics['Clicks'],
                'unique_clicks_count' => $cmMetrics['UniqueClicks'],
                'bounce_count' => $cmMetrics['Bounces'],
                'unsubscribe_count' => $cmMetrics['Unsubscribed'],
                'last_metrics_sync' => now(),
            ]);

            $synced++;

            // Rate limiting
            usleep(500000); // 0.5 second delay

        } catch (\Exception $e) {
            Log::error('Failed to fetch campaign metrics', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    Log::info('Campaign metrics sync complete', [
        'synced' => $synced,
        'discrepancies' => $discrepancies,
    ]);
}
```

---

### Workflow 3: Historical Metrics Import (One-Time)

```
Admin runs command:
php artisan cdp:import-historical-metrics --months=12
    │
    ▼
ImportHistoricalCampaignMetrics command
    │
    ├──► 1. Fetch all CM campaigns from last 12 months
    │        GET /campaigns?page=1&pagesize=1000
    │        GET /campaigns?page=2&pagesize=1000
    │        ...
    │
    ├──► 2. Filter campaigns already in campaigns table
    │        Match by cm_campaign_id
    │
    ├──► 3. For each campaign not in CDP:
    │        │
    │        ├──► a. Fetch campaign details
    │        │      GET /campaigns/{cm_campaign_id}
    │        │
    │        ├──► b. Fetch campaign summary (metrics)
    │        │      GET /campaigns/{cm_campaign_id}/summary
    │        │
    │        ├──► c. Create Campaign record in CDP
    │        │      Campaign::create([
    │        │        'name' => $details['Name'],
    │        │        'cm_campaign_id' => $cmId,
    │        │        'sent_at' => $details['SentDate'],
    │        │        'opens_count' => $summary['TotalOpens'],
    │        │        'clicks_count' => $summary['Clicks'],
    │        │        ...
    │        │      ])
    │        │
    │        └──► d. Create campaign_metrics record
    │               CampaignMetric::create([
    │                 'campaign_id' => $campaign->id,
    │                 'date' => $details['SentDate'],
    │                 'opens' => $summary['TotalOpens'],
    │                 'clicks' => $summary['Clicks'],
    │                 ...
    │               ])
    │
    ├──► 4. Progress bar:
    │        Processing: ████████████░░░░░░░░ 60%
    │        Campaigns imported: 45/75
    │        Estimated time remaining: 2 minutes
    │
    └──► 5. Summary report:
             ✓ Imported 75 historical campaigns
             ✓ Total opens: 125,340
             ✓ Total clicks: 23,450
             ✓ Date range: 2024-01-01 to 2025-11-12
```

**Implementation**:

```php
// app/Console/Commands/ImportHistoricalCampaignMetrics.php

protected $signature = 'cdp:import-historical-metrics {--months=12}';

public function handle(CampaignMonitorService $cmService)
{
    $months = $this->option('months');
    $startDate = now()->subMonths($months);

    $this->info("Fetching CM campaigns from last {$months} months...");

    // Fetch all campaigns from CM
    $cmCampaigns = $cmService->fetchCampaignsSince($startDate);

    $this->info("Found {$cmCampaigns->count()} campaigns in CM");

    // Filter campaigns not in CDP
    $existingIds = Campaign::pluck('cm_campaign_id')->toArray();
    $newCampaigns = $cmCampaigns->reject(fn($c) =>
        in_array($c['CampaignID'], $existingIds)
    );

    $this->info("Importing {$newCampaigns->count()} new campaigns...");

    $bar = $this->output->createProgressBar($newCampaigns->count());

    foreach ($newCampaigns as $cmCampaign) {
        try {
            // Fetch detailed metrics
            $summary = $cmService->fetchCampaignSummary($cmCampaign['CampaignID']);

            // Create campaign
            $campaign = Campaign::create([
                'name' => $cmCampaign['Name'],
                'type' => 'external', // Not created by CDP
                'cm_campaign_id' => $cmCampaign['CampaignID'],
                'sent_at' => $cmCampaign['SentDate'],
                'status' => 'sent',
                'opens_count' => $summary['TotalOpens'] ?? 0,
                'unique_opens_count' => $summary['UniqueOpens'] ?? 0,
                'clicks_count' => $summary['Clicks'] ?? 0,
                'unique_clicks_count' => $summary['UniqueClicks'] ?? 0,
                'bounce_count' => $summary['Bounces'] ?? 0,
                'unsubscribe_count' => $summary['Unsubscribed'] ?? 0,
            ]);

            // Create metrics record
            CampaignMetric::create([
                'campaign_id' => $campaign->id,
                'date' => Carbon::parse($cmCampaign['SentDate'])->toDateString(),
                'opens' => $summary['TotalOpens'] ?? 0,
                'unique_opens' => $summary['UniqueOpens'] ?? 0,
                'clicks' => $summary['Clicks'] ?? 0,
                'unique_clicks' => $summary['UniqueClicks'] ?? 0,
                'bounces' => $summary['Bounces'] ?? 0,
                'unsubscribes' => $summary['Unsubscribed'] ?? 0,
            ]);

            $bar->advance();

        } catch (\Exception $e) {
            $this->error("Failed to import campaign {$cmCampaign['Name']}: {$e->getMessage()}");
        }
    }

    $bar->finish();
    $this->newLine(2);
    $this->info('✓ Historical import complete!');
}
```

---

### Database Schema: campaign_metrics Table

```php
// database/migrations/YYYY_MM_DD_create_campaign_metrics_table.php

Schema::create('campaign_metrics', function (Blueprint $table) {
    $table->id();
    $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
    $table->date('date'); // Metrics grouped by date
    $table->integer('opens')->default(0);
    $table->integer('unique_opens')->default(0);
    $table->integer('clicks')->default(0);
    $table->integer('unique_clicks')->default(0);
    $table->integer('bounces')->default(0);
    $table->integer('unsubscribes')->default(0);
    $table->integer('spam_reports')->default(0);
    $table->timestamps();

    // Composite index for efficient queries
    $table->index(['campaign_id', 'date']);
    $table->index('date');
});
```

### Campaign Model Enhancements

```php
// Add to app/Models/Campaign.php migration

$table->integer('opens_count')->default(0);
$table->integer('unique_opens_count')->default(0);
$table->integer('clicks_count')->default(0);
$table->integer('unique_clicks_count')->default(0);
$table->integer('bounce_count')->default(0);
$table->integer('unsubscribe_count')->default(0);
$table->integer('spam_report_count')->default(0);
$table->timestamp('last_metrics_sync')->nullable();
```

```php
// app/Models/Campaign.php

class Campaign extends Model
{
    public function metrics()
    {
        return $this->hasMany(CampaignMetric::class);
    }

    /**
     * Aggregate metrics from campaign_metrics table
     */
    public function refreshMetrics()
    {
        $this->update([
            'opens_count' => $this->metrics()->sum('opens'),
            'unique_opens_count' => $this->metrics()->max('unique_opens'),
            'clicks_count' => $this->metrics()->sum('clicks'),
            'unique_clicks_count' => $this->metrics()->max('unique_clicks'),
            'bounce_count' => $this->metrics()->sum('bounces'),
            'unsubscribe_count' => $this->metrics()->sum('unsubscribes'),
            'last_metrics_sync' => now(),
        ]);
    }

    /**
     * Calculate open rate percentage
     */
    public function getOpenRateAttribute(): float
    {
        if ($this->recipient_count === 0) return 0;
        return round(($this->unique_opens_count / $this->recipient_count) * 100, 2);
    }

    /**
     * Calculate click rate percentage
     */
    public function getClickRateAttribute(): float
    {
        if ($this->recipient_count === 0) return 0;
        return round(($this->unique_clicks_count / $this->recipient_count) * 100, 2);
    }

    /**
     * Calculate click-to-open rate percentage
     */
    public function getClickToOpenRateAttribute(): float
    {
        if ($this->unique_opens_count === 0) return 0;
        return round(($this->unique_clicks_count / $this->unique_opens_count) * 100, 2);
    }
}
```

---

### API Usage Impact

```
Webhook Events (No API calls):
├── Open events: ~5,000/day (real-time, instant)
├── Click events: ~1,200/day (real-time, instant)
├── Bounce events: ~50/day (real-time, instant)
└── Unsubscribe events: ~20/day (real-time, instant)

Hourly Polling Job:
├── Campaigns checked: 10/hour (only recent campaigns)
├── API calls: 10 × GET /campaigns/{id}/summary = 10 calls/hour
└── Monthly total: 10 × 24 × 30 = 7,200 calls/month

Historical Import (One-time):
├── Fetch 12 months of campaigns: ~1 call (paginated)
├── Fetch details for each: ~75 campaigns × 2 calls = 150 calls
└── Total: ~150 calls (one-time setup)

TOTAL MONTHLY (ongoing): ~7,200 API calls for metrics polling
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

Campaign Metrics Polling:
├── Hourly metrics fetch: 10 campaigns/hour × 24 × 30 = 7,200 calls/month
├── Historical import (one-time): ~150 calls
└── Subtotal: 7,200 calls/month (ongoing)

TOTAL (without metrics): ~478 API calls/month
TOTAL (with metrics polling): ~7,678 API calls/month
Daily average: ~256 API calls/day
```

**Note**: Campaign metrics use the majority of API quota. Webhooks handle real-time events with zero API calls. Polling is backup/validation and can be reduced to every 2-4 hours if API limits are a concern (reducing monthly calls to ~3,600).

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
