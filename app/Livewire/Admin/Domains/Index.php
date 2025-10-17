<?php

namespace App\Livewire\Admin\Domains;

use App\Models\Domain;
use App\Models\Organization;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Log;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $showDeleteModal = false;
    public $domainToDelete = null;
    public $showCreateModal = false;
    public $showEditModal = false;
    public $domainToEdit = null;
    public $showAssociateModal = false;
    public $domainToAssociate = null;
    public $syncingDomain = null;

    // Form fields
    public $domain = '';
    public $organization_id = null;
    public $selectedOrganizations = [];
    public $domainUsers = [];
    public $userOrganizationStats = [];

    protected $queryString = ['search'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $domains = Domain::query()
            ->with('organizations')
            ->withCount('users')
            ->when($this->search, function ($query) {
                $query->where('domain', 'like', '%' . $this->search . '%');
            })
            ->orderBy('users_count', 'desc')
            ->paginate(15);

        $organizations = Organization::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.admin.domains.index', [
            'domains' => $domains,
            'organizations' => $organizations
        ])->layout('components.layouts.app', ['title' => 'Domains']);
    }

    public function openCreateModal()
    {
        $this->reset(['domain', 'organization_id']);
        $this->showCreateModal = true;
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
        $this->reset(['domain', 'organization_id']);
        $this->resetValidation();
    }

    public function createDomain()
    {
        $this->validate([
            'domain' => 'required|string|unique:domains,domain',
            'organization_id' => 'nullable|exists:organizations,id',
        ]);

        try {
            $newDomain = Domain::create([
                'domain' => strtolower(trim($this->domain))
            ]);

            // If organization is provided, associate it
            if ($this->organization_id) {
                $organization = Organization::find($this->organization_id);
                $result = $newDomain->assignUsersToOrganization($organization);
                session()->flash('message', "Domain created and {$result} users assigned.");
            } else {
                session()->flash('message', 'Domain created successfully.');
            }

            $this->closeCreateModal();
        } catch (\Exception $e) {
            Log::error('Failed to create domain', [
                'error' => $e->getMessage(),
                'domain' => $this->domain
            ]);
            session()->flash('error', 'Failed to create domain: ' . $e->getMessage());
        }
    }

    public function openEditModal($id)
    {
        $domain = Domain::with('organizations')->findOrFail($id);
        $this->domainToEdit = $domain;
        $this->domain = $domain->domain;
        $this->selectedOrganizations = $domain->organizations->pluck('id')->toArray();

        // Load users associated with this domain, grouped by organization
        $this->domainUsers = \App\Models\User::where('domain_id', $id)
            ->with(['organization', 'organizations'])
            ->orderBy('organization_id')
            ->orderBy('fullname')
            ->limit(100)
            ->get()
            ->toArray();

        // Calculate organization statistics for users (many-to-many)
        $allUsers = \App\Models\User::where('domain_id', $id)
            ->with('organizations')
            ->get();

        // Count users per organization across all relationships
        $orgStats = [];
        foreach ($allUsers as $user) {
            // Use many-to-many organizations if available, otherwise fall back to legacy organization
            $userOrgs = $user->organizations->count() > 0
                ? $user->organizations
                : ($user->organization ? collect([$user->organization]) : collect());

            foreach ($userOrgs as $org) {
                if (!isset($orgStats[$org->id])) {
                    $orgStats[$org->id] = [
                        'id' => $org->id,
                        'name' => $org->name,
                        'count' => 0,
                    ];
                }
                $orgStats[$org->id]['count']++;
            }
        }

        // If no organizations found, check for users with no organization
        if (empty($orgStats)) {
            $usersWithoutOrg = $allUsers->filter(function($user) {
                return $user->organizations->count() === 0 && !$user->organization;
            })->count();

            if ($usersWithoutOrg > 0) {
                $orgStats[0] = [
                    'id' => null,
                    'name' => 'No Organization',
                    'count' => $usersWithoutOrg,
                ];
            }
        }

        $this->userOrganizationStats = collect($orgStats)
            ->sortByDesc('count')
            ->values()
            ->toArray();

        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->domainToEdit = null;
        $this->reset(['domain', 'selectedOrganizations', 'domainUsers', 'userOrganizationStats']);
        $this->resetValidation();
    }

    public function updateDomain()
    {
        $this->validate([
            'domain' => 'required|string|unique:domains,domain,' . $this->domainToEdit->id,
            'selectedOrganizations' => 'array',
            'selectedOrganizations.*' => 'exists:organizations,id',
        ]);

        try {
            $this->domainToEdit->update([
                'domain' => strtolower(trim($this->domain)),
            ]);

            $orgIds = $this->selectedOrganizations;

            // If organizations are being updated, dispatch background job
            if (!empty($orgIds)) {
                // Create sync log entry
                $syncLog = \App\Models\SyncLog::create([
                    'type' => 'sync_domain_organizations',
                    'status' => 'pending',
                    'user_id' => auth()->id(),
                    'total_items' => 0,
                    'processed_items' => 0,
                    'successful_items' => 0,
                    'failed_items' => 0,
                    'metadata' => [
                        'domain_id' => $this->domainToEdit->id,
                        'domain_name' => $this->domainToEdit->domain,
                        'organization_ids' => $orgIds,
                    ],
                ]);

                // Dispatch the job to run in the background
                \App\Jobs\SyncDomainOrganizationsJob::dispatch(
                    $syncLog->id,
                    $this->domainToEdit->id,
                    $orgIds
                );

                session()->flash('message', "Domain updated. Organization sync started in the background (Sync Log ID: #{$syncLog->id}). <a href='" . route('admin.sync-logs.index') . "' class='underline font-bold'>View Progress</a>");
            } else {
                session()->flash('message', 'Domain updated successfully.');
            }

            $this->closeEditModal();
        } catch (\Exception $e) {
            Log::error('Failed to update domain', [
                'error' => $e->getMessage(),
                'domain_id' => $this->domainToEdit->id
            ]);
            session()->flash('error', 'Failed to update domain: ' . $e->getMessage());
        }
    }

    public function syncDomain($id)
    {
        $this->syncingDomain = $id;

        try {
            $domain = Domain::findOrFail($id);
            $result = $domain->assignUsersFromDomain();

            Log::info('Domain manually synced', [
                'domain' => $domain->domain,
                'assigned_count' => $result['assigned_count'],
                'total_users' => $result['total_users']
            ]);

            session()->flash('message', "Synced successfully. {$result['assigned_count']} users updated out of {$result['total_users']} total.");
        } catch (\Exception $e) {
            Log::error('Failed to sync domain', [
                'domain_id' => $id,
                'error' => $e->getMessage()
            ]);
            session()->flash('error', 'Failed to sync domain: ' . $e->getMessage());
        } finally {
            $this->syncingDomain = null;
        }
    }

    public function syncAllDomains()
    {
        try {
            // Create sync log entry
            $syncLog = \App\Models\SyncLog::create([
                'type' => 'sync_all_domains',
                'status' => 'pending',
                'user_id' => auth()->id(),
            ]);

            // Dispatch the job to run in the background
            \App\Jobs\SyncAllDomainsJob::dispatch($syncLog->id);

            Log::info('Sync all domains job dispatched', [
                'user_id' => auth()->id(),
                'sync_log_id' => $syncLog->id
            ]);

            session()->flash('message', "Domain sync started in the background (ID: #{$syncLog->id}). Check the sync logs to monitor progress.");
        } catch (\Exception $e) {
            Log::error('Failed to dispatch sync all domains job', [
                'error' => $e->getMessage()
            ]);
            session()->flash('error', 'Failed to start domain sync: ' . $e->getMessage());
        }
    }

    public function confirmDelete($id)
    {
        $this->domainToDelete = Domain::findOrFail($id);
        $this->showDeleteModal = true;
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->domainToDelete = null;
    }

    public function deleteDomain()
    {
        if ($this->domainToDelete) {
            try {
                $domainName = $this->domainToDelete->domain;
                $this->domainToDelete->delete();
                session()->flash('message', "Domain '{$domainName}' deleted successfully.");
                $this->cancelDelete();
            } catch (\Exception $e) {
                Log::error('Failed to delete domain', [
                    'error' => $e->getMessage()
                ]);
                session()->flash('error', 'Failed to delete domain: ' . $e->getMessage());
            }
        }
    }

    public function openAssociateModal($id)
    {
        $this->domainToAssociate = Domain::with('organizations')->findOrFail($id);
        $this->selectedOrganizations = $this->domainToAssociate->organizations->pluck('id')->toArray();
        $this->showAssociateModal = true;
    }

    public function closeAssociateModal()
    {
        $this->showAssociateModal = false;
        $this->domainToAssociate = null;
        $this->selectedOrganizations = [];
    }

    public function updateAssociations()
    {
        $this->validate([
            'selectedOrganizations' => 'array',
            'selectedOrganizations.*' => 'exists:organizations,id',
        ]);

        try {
            $orgIds = $this->selectedOrganizations;

            // If organizations are being updated, dispatch background job
            if (!empty($orgIds)) {
                // Create sync log entry
                $syncLog = \App\Models\SyncLog::create([
                    'type' => 'sync_domain_organizations',
                    'status' => 'pending',
                    'user_id' => auth()->id(),
                    'total_items' => 0,
                    'processed_items' => 0,
                    'successful_items' => 0,
                    'failed_items' => 0,
                    'metadata' => [
                        'domain_id' => $this->domainToAssociate->id,
                        'domain_name' => $this->domainToAssociate->domain,
                        'organization_ids' => $orgIds,
                    ],
                ]);

                // Dispatch the job to run in the background
                \App\Jobs\SyncDomainOrganizationsJob::dispatch(
                    $syncLog->id,
                    $this->domainToAssociate->id,
                    $orgIds
                );

                session()->flash('message', "Organization sync started in the background (Sync Log ID: #{$syncLog->id}). <a href='" . route('admin.sync-logs.index') . "' class='underline font-bold'>View Progress</a>");
            } else {
                session()->flash('message', 'Organizations updated successfully.');
            }

            $this->closeAssociateModal();
        } catch (\Exception $e) {
            Log::error('Failed to update organization associations', [
                'error' => $e->getMessage()
            ]);
            session()->flash('error', 'Failed to update associations: ' . $e->getMessage());
        }
    }
}
