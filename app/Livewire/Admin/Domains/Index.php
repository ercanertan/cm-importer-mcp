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
    public $activeSyncLogId = null;
    public $syncStatus = null;
    public $pollingInterval = null;

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

    /**
     * Check the status of active sync job and stop polling when complete
     */
    public function checkSyncStatus()
    {
        if (!$this->activeSyncLogId) {
            $this->pollingInterval = null;
            return;
        }

        $syncLog = \App\Models\SyncLog::find($this->activeSyncLogId);

        if (!$syncLog) {
            $this->activeSyncLogId = null;
            $this->pollingInterval = null;
            return;
        }

        $this->syncStatus = [
            'status' => $syncLog->status,
            'progress' => $syncLog->processed_items && $syncLog->total_items
                ? round(($syncLog->processed_items / $syncLog->total_items) * 100, 1)
                : 0,
            'processed' => $syncLog->processed_items ?? 0,
            'total' => $syncLog->total_items ?? 0,
            'successful' => $syncLog->successful_items ?? 0,
            'failed' => $syncLog->failed_items ?? 0,
        ];

        // Stop polling if job is completed or failed
        if (in_array($syncLog->status, ['completed', 'failed'])) {
            $this->pollingInterval = null;
            $this->activeSyncLogId = null;

            // Refresh the domains list to show updated data
            $this->dispatch('$refresh');
        }
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

            // If organization is provided, dispatch background job to sync users
            if ($this->organization_id) {
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
                        'domain_id' => $newDomain->id,
                        'domain_name' => $newDomain->domain,
                        'organization_ids' => [$this->organization_id],
                    ],
                ]);

                // Dispatch the job to run in the background
                \App\Jobs\SyncDomainOrganizationsJob::dispatch(
                    $syncLog->id,
                    $newDomain->id,
                    [$this->organization_id]
                );

                // Start polling for status updates
                $this->activeSyncLogId = $syncLog->id;
                $this->pollingInterval = 2000; // Poll every 2 seconds

                session()->flash('message', "Domain created successfully. User sync started in the background (Sync Log ID: #{$syncLog->id}). <a href='" . route('admin.sync-logs.index') . "' class='underline font-bold'>View Progress</a>");
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

        // Load users associated with this domain, grouped by organization (LIMIT 100 for preview)
        $this->domainUsers = \App\Models\User::where('domain_id', $id)
            ->with(['organization', 'organizations'])
            ->orderBy('organization_id')
            ->orderBy('fullname')
            ->limit(100)
            ->get()
            ->toArray();

        // OPTIMIZED: Calculate organization statistics using database queries instead of loading all users
        // This is MUCH faster for domains with 10k+ users
        $orgStats = \Illuminate\Support\Facades\DB::table('organization_user')
            ->join('users', 'organization_user.user_id', '=', 'users.id')
            ->join('organizations', 'organization_user.organization_id', '=', 'organizations.id')
            ->where('users.domain_id', $id)
            ->select(
                'organizations.id',
                'organizations.name',
                \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT users.id) as count')
            )
            ->groupBy('organizations.id', 'organizations.name')
            ->orderByDesc('count')
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'count' => (int) $item->count,
                ];
            })
            ->toArray();

        // Check for users without any organization (fast count query)
        $usersWithoutOrg = \Illuminate\Support\Facades\DB::table('users')
            ->leftJoin('organization_user', 'users.id', '=', 'organization_user.user_id')
            ->where('users.domain_id', $id)
            ->whereNull('organization_user.user_id')
            ->count();

        if ($usersWithoutOrg > 0) {
            $orgStats[] = [
                'id' => null,
                'name' => 'No Organization',
                'count' => $usersWithoutOrg,
            ];
        }

        $this->userOrganizationStats = $orgStats;

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

                // Start polling for status updates
                $this->activeSyncLogId = $syncLog->id;
                $this->pollingInterval = 2000; // Poll every 2 seconds

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
        try {
            $domain = Domain::findOrFail($id);

            // Create sync log entry
            $syncLog = \App\Models\SyncLog::create([
                'type' => 'sync_single_domain',
                'status' => 'pending',
                'user_id' => auth()->id(),
                'total_items' => 0,
                'processed_items' => 0,
                'successful_items' => 0,
                'failed_items' => 0,
                'metadata' => [
                    'domain_id' => $id,
                    'domain_name' => $domain->domain,
                ],
            ]);

            // Dispatch the job to run in the background
            \App\Jobs\SyncSingleDomainJob::dispatch($syncLog->id, $id);

            // Start polling for status updates
            $this->activeSyncLogId = $syncLog->id;
            $this->pollingInterval = 2000; // Poll every 2 seconds

            Log::info('Sync single domain job dispatched', [
                'user_id' => auth()->id(),
                'domain_id' => $id,
                'domain' => $domain->domain,
                'sync_log_id' => $syncLog->id
            ]);

            session()->flash('message', "Domain sync started in the background (Sync Log ID: #{$syncLog->id}). <a href='" . route('admin.sync-logs.index') . "' class='underline font-bold'>View Progress</a>");
        } catch (\Exception $e) {
            Log::error('Failed to dispatch sync domain job', [
                'domain_id' => $id,
                'error' => $e->getMessage()
            ]);
            session()->flash('error', 'Failed to start domain sync: ' . $e->getMessage());
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

                // Start polling for status updates
                $this->activeSyncLogId = $syncLog->id;
                $this->pollingInterval = 2000; // Poll every 2 seconds

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
