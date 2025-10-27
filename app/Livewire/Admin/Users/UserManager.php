<?php

namespace App\Livewire\Admin\Users;

use App\Models\User;
use App\Models\Organization;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

class UserManager extends Component
{
    use WithPagination;

    public $search = '';
    public $showManageModal = false;
    public $showPrimaryOrgModal = false;
    public $userToManage = null;
    public $selectedOrganizations = [];
    public $autoAssignedOrgs = [];
    public $manuallyAssignedOrgs = [];
    public $primaryOrganizationId = null;
    public $userOrganizations = [];

    protected $queryString = ['search'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $users = User::query()
            ->with(['organizations', 'domain'])
            ->withCount('organizations')
            ->when($this->search, function ($query) {
                $query->where('fullname', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%');
            })
            ->orderBy('fullname')
            ->paginate(20);

        $organizations = Organization::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.admin.users.user-manager', [
            'users' => $users,
            'organizations' => $organizations
        ])->layout('components.layouts.app', ['title' => 'Users']);
    }

    public function openManageModal($userId)
    {
        $this->userToManage = User::with('organizations', 'domain')->findOrFail($userId);

        // Get organizations with pivot data to distinguish manual vs auto-assigned
        $userOrgs = DB::table('organization_user')
            ->where('user_id', $userId)
            ->get();

        $this->autoAssignedOrgs = [];
        $this->manuallyAssignedOrgs = [];

        foreach ($userOrgs as $pivot) {
            if ($pivot->is_manual) {
                $this->manuallyAssignedOrgs[] = $pivot->organization_id;
            } else {
                $this->autoAssignedOrgs[] = $pivot->organization_id;
            }
        }

        // Selected organizations include both auto and manual
        $this->selectedOrganizations = array_merge($this->autoAssignedOrgs, $this->manuallyAssignedOrgs);

        $this->showManageModal = true;
    }

    public function closeManageModal()
    {
        $this->showManageModal = false;
        $this->userToManage = null;
        $this->selectedOrganizations = [];
        $this->autoAssignedOrgs = [];
        $this->manuallyAssignedOrgs = [];
    }

    public function updateOrganizations()
    {
        $this->validate([
            'selectedOrganizations' => 'array',
            'selectedOrganizations.*' => 'exists:organizations,id',
        ]);

        try {
            // Prepare sync data
            $syncData = [];

            foreach ($this->selectedOrganizations as $orgId) {
                // If it's in auto-assigned, keep it as auto (is_manual = false)
                // If it's NOT in auto-assigned, it's manually added (is_manual = true)
                $isManual = !in_array($orgId, $this->autoAssignedOrgs);
                $syncData[$orgId] = ['is_manual' => $isManual];
            }

            // Sync organizations (will preserve manual flag correctly)
            $this->userToManage->organizations()->sync($syncData);

            // Update legacy organization_id field if needed
            if (!empty($this->selectedOrganizations)) {
                $this->userToManage->organization_id = $this->selectedOrganizations[0];
                $this->userToManage->save();
            }

            session()->flash('message', 'User organizations updated successfully.');
            $this->closeManageModal();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update user organizations', [
                'error' => $e->getMessage(),
                'user_id' => $this->userToManage->id
            ]);
            session()->flash('error', 'Failed to update organizations: ' . $e->getMessage());
        }
    }

    public function openPrimaryOrgModal($userId)
    {
        $this->userToManage = User::with('organizations')->findOrFail($userId);

        // Load user's organizations with primary status
        $this->userOrganizations = $this->userToManage->organizations()
            ->get()
            ->map(function ($org) {
                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'is_primary' => $org->pivot->is_primary,
                    'is_manual' => $org->pivot->is_manual,
                ];
            })
            ->toArray();

        // Get current primary organization
        $primaryOrg = collect($this->userOrganizations)->firstWhere('is_primary', true);
        $this->primaryOrganizationId = $primaryOrg['id'] ?? null;

        $this->showPrimaryOrgModal = true;
    }

    public function closePrimaryOrgModal()
    {
        $this->showPrimaryOrgModal = false;
        $this->userToManage = null;
        $this->userOrganizations = [];
        $this->primaryOrganizationId = null;
    }

    public function setPrimary($organizationId)
    {
        if (!$this->userToManage) {
            session()->flash('error', 'No user selected.');
            return;
        }

        // Attempt to set the primary organization
        if ($this->userToManage->setPrimaryOrganization($organizationId)) {
            // Reload user organizations
            $this->userOrganizations = $this->userToManage->fresh()->organizations()
                ->get()
                ->map(function ($org) {
                    return [
                        'id' => $org->id,
                        'name' => $org->name,
                        'is_primary' => $org->pivot->is_primary,
                        'is_manual' => $org->pivot->is_manual,
                    ];
                })
                ->toArray();

            // Update primary organization ID
            $primaryOrg = collect($this->userOrganizations)->firstWhere('is_primary', true);
            $this->primaryOrganizationId = $primaryOrg['id'] ?? null;

            session()->flash('message', 'Primary organization updated successfully for ' . $this->userToManage->fullname . '.');
        } else {
            session()->flash('error', 'Failed to update primary organization.');
        }
    }
}
