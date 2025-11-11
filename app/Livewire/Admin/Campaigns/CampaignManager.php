<?php

namespace App\Livewire\Admin\Campaigns;

use App\Jobs\RecalculateEngagementScoresJob;
use App\Jobs\SyncUsersToMonitorJob;
use App\Models\CmCampaign;
use App\Models\Organization;
use App\Models\User;
use App\Services\CampaignTagService;
use App\Services\CmCampaignStatsService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class CampaignManager extends Component
{
    use WithPagination;

    // Campaign creation
    public $campaignName = '';
    public $selectedTab = 'create'; // create, active, helper, backfill, stats

    // Filters for segment builder
    public $tier = '';
    public $minEngagement = '';
    public $maxEngagement = '';
    public $cmStatus = 'active';
    public $lastActivityDays = '';
    public $organizationId = '';

    // Preview data
    public $previewCount = 0;
    public $previewUsers = [];
    public $showPreview = false;

    // Active campaigns
    public $activeCampaigns = [];
    public $selectedCampaign = null;
    public $campaignStats = null;

    // Helper methods state
    public $helperType = 'highly_engaged';
    public $helperTier = 'paid_premium';
    public $helperThreshold = 70;
    public $helperCampaignName = '';

    // Campaign stats state
    public $importingStats = false;
    public $statsFilter = 'all'; // all, high, medium, low
    public $selectedCmCampaign = null;
    public $cmConnectionStatus = null; // 'checking', 'connected', 'error'
    public $cmConnectionError = null;

    protected CampaignTagService $campaignTagService;

    public function boot(CampaignTagService $campaignTagService)
    {
        $this->campaignTagService = $campaignTagService;
    }

    public function mount()
    {
        $this->loadActiveCampaigns();
    }

    #[Computed]
    public function organizations()
    {
        return Organization::orderBy('name')->get();
    }

    public function updatedSelectedTab()
    {
        if ($this->selectedTab === 'active') {
            $this->loadActiveCampaigns();
        }
    }

    public function loadActiveCampaigns()
    {
        $this->activeCampaigns = $this->campaignTagService->getAllTagsStats();
    }

    public function previewQuery()
    {
        $this->validate([
            'tier' => 'nullable|string',
            'minEngagement' => 'nullable|numeric|min:0|max:100',
            'maxEngagement' => 'nullable|numeric|min:0|max:100',
            'cmStatus' => 'nullable|string',
            'lastActivityDays' => 'nullable|integer|min:1',
            'organizationId' => 'nullable|exists:organizations,id',
        ]);

        $query = User::query();

        if ($this->tier) {
            $query->where('tier', $this->tier);
        }
        if ($this->minEngagement !== '') {
            $query->where('engagement_score', '>=', $this->minEngagement);
        }
        if ($this->maxEngagement !== '') {
            $query->where('engagement_score', '<=', $this->maxEngagement);
        }
        if ($this->cmStatus) {
            $query->where('cm_status', $this->cmStatus);
        }
        if ($this->lastActivityDays) {
            $date = now()->subDays($this->lastActivityDays);
            $query->where('last_activity_at', '>=', $date);
        }
        if ($this->organizationId) {
            $query->where('organization_id', $this->organizationId);
        }

        $this->previewCount = $query->count();
        $this->previewUsers = $query->limit(5)->get(['id', 'fullname', 'email', 'tier', 'engagement_score', 'cm_status']);
        $this->showPreview = true;
    }

    public function createCampaign()
    {
        $this->validate([
            'campaignName' => 'required|string|max:255',
        ]);

        $query = User::query();

        if ($this->tier) {
            $query->where('tier', $this->tier);
        }
        if ($this->minEngagement !== '') {
            $query->where('engagement_score', '>=', $this->minEngagement);
        }
        if ($this->maxEngagement !== '') {
            $query->where('engagement_score', '<=', $this->maxEngagement);
        }
        if ($this->cmStatus) {
            $query->where('cm_status', $this->cmStatus);
        }
        if ($this->lastActivityDays) {
            $date = now()->subDays($this->lastActivityDays);
            $query->where('last_activity_at', '>=', $date);
        }
        if ($this->organizationId) {
            $query->where('organization_id', $this->organizationId);
        }

        try {
            $result = $this->campaignTagService->tagUsersFromQuery($query, $this->campaignName);

            session()->flash('success', "Campaign '{$this->campaignName}' created successfully! Tagged {$result['tagged']} users.");

            // Reset form
            $this->reset(['campaignName', 'tier', 'minEngagement', 'maxEngagement', 'lastActivityDays', 'organizationId', 'showPreview', 'previewCount', 'previewUsers']);
            $this->cmStatus = 'active';

            // Switch to active tab
            $this->selectedTab = 'active';
            $this->loadActiveCampaigns();

        } catch (\Exception $e) {
            session()->flash('error', 'Error creating campaign: ' . $e->getMessage());
        }
    }

    public function viewCampaignStats($campaignTag)
    {
        $this->selectedCampaign = $campaignTag;
        $this->campaignStats = $this->campaignTagService->getTagStats($campaignTag);
    }

    public function clearCampaign($campaignTag)
    {
        try {
            $count = $this->campaignTagService->clearTag($campaignTag);
            session()->flash('success', "Cleared campaign tag from {$count} users.");
            $this->loadActiveCampaigns();
            $this->selectedCampaign = null;
            $this->campaignStats = null;
        } catch (\Exception $e) {
            session()->flash('error', 'Error clearing campaign: ' . $e->getMessage());
        }
    }

    public function createHelperCampaign()
    {
        $this->validate([
            'helperCampaignName' => 'required|string|max:255',
            'helperTier' => 'required|string',
            'helperThreshold' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $result = null;

            switch ($this->helperType) {
                case 'highly_engaged':
                    $result = $this->campaignTagService->tagHighlyEngagedByTier(
                        $this->helperTier,
                        $this->helperThreshold,
                        $this->helperCampaignName
                    );
                    break;

                case 'disengaged':
                    $result = $this->campaignTagService->tagDisengagedUsers(
                        $this->helperThreshold,
                        $this->helperCampaignName
                    );
                    break;
            }

            if ($result) {
                session()->flash('success', "Campaign '{$this->helperCampaignName}' created! Tagged {$result['tagged']} users.");
                $this->reset(['helperCampaignName', 'helperThreshold']);
                $this->helperType = 'highly_engaged';
                $this->helperTier = 'paid_premium';
                $this->selectedTab = 'active';
                $this->loadActiveCampaigns();
            }

        } catch (\Exception $e) {
            session()->flash('error', 'Error creating campaign: ' . $e->getMessage());
        }
    }

    public function syncAllUsersToCm()
    {
        try {
            $unsyncedCount = User::where(function ($query) {
                $query->whereNull('cm_subscriber_id')
                      ->orWhere('cm_status', '!=', 'active');
            })->count();

            if ($unsyncedCount === 0) {
                session()->flash('success', 'All users are already synced to Campaign Monitor.');
                return;
            }

            // Dispatch background job
            SyncUsersToMonitorJob::dispatch();

            session()->flash('success', "Queued {$unsyncedCount} users for sync to Campaign Monitor. This will process in the background.");

        } catch (\Exception $e) {
            session()->flash('error', 'Error queueing sync: ' . $e->getMessage());
        }
    }

    public function recalculateEngagementScores()
    {
        try {
            $userCount = User::count();

            if ($userCount === 0) {
                session()->flash('success', 'No users found to recalculate.');
                return;
            }

            // Dispatch background job with mark-for-sync enabled
            RecalculateEngagementScoresJob::dispatch(markForSync: true);

            session()->flash('success', "Queued engagement score recalculation for {$userCount} users. This will process in the background and mark changed users for tag sync.");

        } catch (\Exception $e) {
            session()->flash('error', 'Error queueing engagement recalculation: ' . $e->getMessage());
        }
    }

    public function syncAllPermanentTags()
    {
        try {
            // Mark all users for tag sync
            $updated = User::where('cm_status', 'active')->update([
                'cm_tags_need_sync' => true
            ]);

            session()->flash('success', "Marked {$updated} active users for tag sync. The scheduled command will sync them in batches.");

        } catch (\Exception $e) {
            session()->flash('error', 'Error marking users for sync: ' . $e->getMessage());
        }
    }

    // Campaign Statistics Methods

    public function checkCmConnection()
    {
        $this->cmConnectionStatus = 'checking';
        $this->cmConnectionError = null;

        try {
            $statsService = app(CmCampaignStatsService::class);

            if (!$statsService->isConfigured()) {
                $this->cmConnectionStatus = 'error';
                $this->cmConnectionError = 'Campaign Monitor API credentials not configured. Please set CM_API_KEY and CM_CLIENT_ID in your .env file.';
                return;
            }

            // Try to fetch campaigns to verify connection
            $campaigns = $statsService->fetchAllCampaigns(1);

            $this->cmConnectionStatus = 'connected';

        } catch (\Exception $e) {
            $this->cmConnectionStatus = 'error';
            $this->cmConnectionError = 'Failed to connect to Campaign Monitor API: ' . $e->getMessage();
        }
    }

    #[Computed]
    public function cmCampaigns()
    {
        $query = CmCampaign::active()->orderBy('sent_at', 'desc');

        if ($this->statsFilter !== 'all') {
            // Filter by engagement level
            $query->where(function ($q) {
                $avgRate = '(open_rate + click_rate) / 2';

                switch ($this->statsFilter) {
                    case 'high':
                        $q->whereRaw("({$avgRate}) >= 30");
                        break;
                    case 'medium':
                        $q->whereRaw("({$avgRate}) >= 15 AND ({$avgRate}) < 30");
                        break;
                    case 'low':
                        $q->whereRaw("({$avgRate}) < 15");
                        break;
                }
            });
        }

        return $query->paginate(20);
    }

    #[Computed]
    public function campaignSummary()
    {
        $campaigns = CmCampaign::active()->get();

        return [
            'total_campaigns' => $campaigns->count(),
            'total_recipients' => $campaigns->sum('total_recipients'),
            'total_opens' => $campaigns->sum('total_opens'),
            'total_clicks' => $campaigns->sum('total_clicks'),
            'avg_open_rate' => round($campaigns->avg('open_rate'), 2),
            'avg_click_rate' => round($campaigns->avg('click_rate'), 2),
        ];
    }

    public function importCampaignStats()
    {
        try {
            $this->importingStats = true;

            $statsService = app(CmCampaignStatsService::class);

            if (!$statsService->isConfigured()) {
                session()->flash('error', 'Campaign Monitor API is not configured. Please set CM_API_KEY and CM_CLIENT_ID in your .env file.');
                $this->importingStats = false;
                return;
            }

            // Import up to 50 most recent campaigns
            $result = $statsService->importAllCampaigns(50);

            session()->flash('success', "Imported {$result['imported']} campaigns successfully! Skipped: {$result['skipped']}, Failed: {$result['failed']}");

            $this->importingStats = false;

        } catch (\Exception $e) {
            session()->flash('error', 'Error importing campaign stats: ' . $e->getMessage());
            $this->importingStats = false;
        }
    }

    public function syncCampaignStats()
    {
        try {
            $statsService = app(CmCampaignStatsService::class);

            if (!$statsService->isConfigured()) {
                session()->flash('error', 'Campaign Monitor API is not configured.');
                return;
            }

            $result = $statsService->syncCampaignStats(50);

            session()->flash('success', "Synced {$result['synced']} campaigns successfully! Failed: {$result['failed']}");

        } catch (\Exception $e) {
            session()->flash('error', 'Error syncing campaign stats: ' . $e->getMessage());
        }
    }

    public function viewCmCampaign($campaignId)
    {
        $this->selectedCmCampaign = CmCampaign::find($campaignId);
    }

    public function closeCmCampaignModal()
    {
        $this->selectedCmCampaign = null;
    }

    public function render()
    {
        return view('livewire.admin.campaigns.campaign-manager');
    }
}
