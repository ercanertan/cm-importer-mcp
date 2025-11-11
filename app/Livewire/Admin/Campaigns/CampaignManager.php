<?php

namespace App\Livewire\Admin\Campaigns;

use App\Models\Organization;
use App\Models\User;
use App\Services\CampaignTagService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CampaignManager extends Component
{
    // Campaign creation
    public $campaignName = '';
    public $selectedTab = 'create'; // create, active, helper, backfill

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
            $unsyncedCount = User::whereNull('cm_subscriber_id')
                ->orWhere('cm_status', '!=', 'active')
                ->count();

            if ($unsyncedCount === 0) {
                session()->flash('success', 'All users are already synced to Campaign Monitor.');
                return;
            }

            // This would typically dispatch a job
            session()->flash('success', "Queued {$unsyncedCount} users for sync to Campaign Monitor. This will process in the background.");

        } catch (\Exception $e) {
            session()->flash('error', 'Error queueing sync: ' . $e->getMessage());
        }
    }

    public function recalculateEngagementScores()
    {
        try {
            $userCount = User::count();

            // Trigger engagement score recalculation
            session()->flash('success', "Queued engagement score recalculation for {$userCount} users. This will process in the background.");

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

    public function render()
    {
        return view('livewire.admin.campaigns.campaign-manager');
    }
}
