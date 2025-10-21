<?php

namespace App\Livewire\Admin\Organizations;

use App\Models\Organization;
use Livewire\Component;
use Livewire\WithPagination;

class OrganizationManager extends Component
{
    use WithPagination;

    public $search = '';
    public $showDeleteModal = false;

    #[\Livewire\Attributes\Locked]
    public $organizationToDelete = null;

    public $showCreateModal = false;
    public $showAdvancedCreateModal = false;
    public $showEditModal = false;
    public $showAdvancedEditModal = false;

    #[\Livewire\Attributes\Locked]
    public $organizationToEdit = null;

    #[\Livewire\Attributes\Locked]
    public $organizationToEditAdvanced = null;

    public $showManageUsersModal = false;

    #[\Livewire\Attributes\Locked]
    public $organizationToManageId = null;
    public $userSearch = '';
    public $selectedUsers = [];
    // REMOVED: No longer loading ALL user IDs into memory
    // public $autoAssignedUsers = [];
    // public $manuallyAssignedUsers = [];
    public $userPage = 1;
    public $usersPerPage = 20; // Increased from 5 to 20 for better UX
    public $assignedUsersPage = 1;
    public $assignedUsersPerPage = 20; // Increased from 5 to 20 for better UX

    // Polling properties for background job tracking
    public $activeSyncLogId = null;
    public $syncStatus = null;
    public $pollingInterval = null;

    // Form fields
    public $name = '';
    public $description = '';
    public $is_active = true;
    public $domains = '';
    public $syncUsers = true;

    // Advanced create fields
    public $conditions = [];
    public $conditionLogic = 'AND'; // AND or OR

    protected $queryString = ['search'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    /**
     * Livewire hydrate hook - ensures conditions array is properly normalized
     * This prevents stdClass objects from breaking array access in Blade templates
     */
    public function hydrate()
    {
        // Normalize conditions to plain array after hydration
        // This is crucial for the disabled attribute check in the value input field
        if (isset($this->conditions) && is_array($this->conditions)) {
            $this->conditions = json_decode(json_encode($this->conditions), true);
        }
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

            // Refresh the organizations list to show updated data
            $this->dispatch('$refresh');
        }
    }

    public function render()
    {
        $organizations = Organization::query()
            ->withCount(['usersMany as users_count', 'domains'])
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.organizations.organization-manager', [
            'organizations' => $organizations
        ])->layout('components.layouts.app', ['title' => 'Organizations']);
    }

    public function openCreateModal()
    {
        $this->reset(['name', 'description', 'is_active', 'domains', 'syncUsers']);
        $this->is_active = true;
        $this->syncUsers = true;
        $this->showCreateModal = true;
    }

    public function openAdvancedCreateModal()
    {
        $this->reset(['name', 'description', 'is_active', 'domains', 'conditions', 'conditionLogic']);
        $this->is_active = true;
        $this->conditionLogic = 'AND';
        $this->conditions = []; // Start with empty conditions

        $this->showAdvancedCreateModal = true;
    }

    public function closeAdvancedCreateModal()
    {
        $this->showAdvancedCreateModal = false;
        $this->reset(['name', 'description', 'is_active', 'domains', 'conditions', 'conditionLogic']);
        $this->resetValidation();
    }

    public function openAdvancedEditModal($id)
    {
        $organization = Organization::with('domains')->findOrFail($id);

        if (!$organization->hasConditionalRules()) {
            session()->flash('error', 'This organization was not created with conditional rules.');
            return;
        }

        $this->name = $organization->name;
        $this->description = $organization->description;
        $this->is_active = $organization->is_active;
        $this->domains = $organization->domains->pluck('domain')->implode(', ');
        $this->conditionLogic = $organization->getConditionLogic();
        $this->conditions = $organization->getConditions();

        // Store only ID and name (using #[Locked] attribute for security)
        $this->organizationToEditAdvanced = $organization->only(['id', 'name']);

        $this->showAdvancedEditModal = true;
    }

    public function closeAdvancedEditModal()
    {
        $this->showAdvancedEditModal = false;
        $this->organizationToEditAdvanced = null;
        $this->reset(['name', 'description', 'is_active', 'domains', 'conditions', 'conditionLogic']);
        $this->resetValidation();
    }

    public function updateAdvancedOrganization()
    {
        $orgId = is_array($this->organizationToEditAdvanced) ? $this->organizationToEditAdvanced['id'] : $this->organizationToEditAdvanced->id;

        $this->validate([
            'name' => 'required|string|max:255|unique:organizations,name,' . $orgId,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'domains' => 'required|string',
            'conditionLogic' => 'required|in:AND,OR',
            'conditions' => 'required|array|min:1',
            'conditions.*.field_id' => 'required|exists:cm_custom_fields,id',
            'conditions.*.operator' => 'required|in:equals,not_equals,contains,not_contains,starts_with,ends_with,is_empty,is_not_empty',
            'conditions.*.value' => 'nullable|string',
        ]);

        try {
            // Reload organization to avoid serialization issues
            $organization = Organization::findOrFail($orgId);

            $organization->update([
                'name' => $this->name,
                'description' => $this->description,
                'is_active' => $this->is_active,
                'conditional_rules' => [
                    'logic' => $this->conditionLogic,
                    'conditions' => $this->conditions,
                ],
            ]);

            // Parse domains from comma-separated string
            $domainList = array_map('trim', explode(',', $this->domains));
            $domainList = array_filter($domainList);

            // Create sync log entry for re-syncing users with updated conditions
            $syncLog = \App\Models\SyncLog::create([
                'type' => 'sync_organization_conditional',
                'status' => 'pending',
                'user_id' => auth()->id(),
                'total_items' => 0,
                'processed_items' => 0,
                'successful_items' => 0,
                'failed_items' => 0,
                'metadata' => [
                    'organization_id' => $organization->id,
                    'organization_name' => $organization->name,
                    'domains' => $domainList,
                    'conditions' => $this->conditions,
                    'condition_logic' => $this->conditionLogic,
                    'action' => 'update',
                ],
            ]);

            // Dispatch the job to re-sync users based on updated conditions
            \App\Jobs\SyncOrganizationConditionalJob::dispatch(
                $syncLog->id,
                $organization->id,
                $domainList,
                $this->conditions,
                $this->conditionLogic
            );

            // Start polling for status updates
            $this->activeSyncLogId = $syncLog->id;
            $this->pollingInterval = 2000; // Poll every 2 seconds

            session()->flash('message', "Advanced organization updated successfully. Conditional user re-sync started in the background (Sync Log ID: #{$syncLog->id}). <a href='" . route('admin.sync-logs.index') . "' class='underline font-bold'>View Progress</a>");

            $this->closeAdvancedEditModal();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update advanced organization', [
                'error' => $e->getMessage(),
                'organization_id' => $orgId
            ]);
            session()->flash('error', 'Failed to update advanced organization: ' . $e->getMessage());
        }
    }

    public function addCondition()
    {
        $this->conditions[] = [
            'field_id' => null,
            'operator' => 'equals',
            'value' => ''
        ];
    }

    public function removeCondition($index)
    {
        unset($this->conditions[$index]);
        $this->conditions = array_values($this->conditions); // Re-index array
    }

    #[\Livewire\Attributes\Computed]
    public function availableCustomFields()
    {
        return \App\Models\CmCustomField::active()
            ->orderBy('field_name')
            ->get();
    }

    public function createAdvancedOrganization()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:organizations,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'domains' => 'required|string',
            'conditionLogic' => 'required|in:AND,OR',
            'conditions' => 'required|array|min:1',
            'conditions.*.field_id' => 'required|exists:cm_custom_fields,id',
            'conditions.*.operator' => 'required|in:equals,not_equals,contains,not_contains,starts_with,ends_with,is_empty,is_not_empty',
            'conditions.*.value' => 'nullable|string',
        ]);

        try {
            $organization = Organization::create([
                'name' => $this->name,
                'description' => $this->description,
                'is_active' => $this->is_active,
                'conditional_rules' => [
                    'logic' => $this->conditionLogic,
                    'conditions' => $this->conditions,
                ],
            ]);

            // Parse domains from comma-separated string
            $domainList = array_map('trim', explode(',', $this->domains));
            $domainList = array_filter($domainList);

            // Create sync log entry
            $syncLog = \App\Models\SyncLog::create([
                'type' => 'sync_organization_conditional',
                'status' => 'pending',
                'user_id' => auth()->id(),
                'total_items' => 0,
                'processed_items' => 0,
                'successful_items' => 0,
                'failed_items' => 0,
                'metadata' => [
                    'organization_id' => $organization->id,
                    'organization_name' => $organization->name,
                    'domains' => $domainList,
                    'conditions' => $this->conditions,
                    'condition_logic' => $this->conditionLogic,
                ],
            ]);

            // Dispatch the job to run in the background
            \App\Jobs\SyncOrganizationConditionalJob::dispatch(
                $syncLog->id,
                $organization->id,
                $domainList,
                $this->conditions,
                $this->conditionLogic
            );

            // Start polling for status updates
            $this->activeSyncLogId = $syncLog->id;
            $this->pollingInterval = 2000; // Poll every 2 seconds

            session()->flash('message', "Advanced organization created successfully. Conditional user sync started in the background (Sync Log ID: #{$syncLog->id}). <a href='" . route('admin.sync-logs.index') . "' class='underline font-bold'>View Progress</a>");

            $this->closeAdvancedCreateModal();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to create advanced organization', [
                'error' => $e->getMessage(),
                'name' => $this->name
            ]);
            session()->flash('error', 'Failed to create advanced organization: ' . $e->getMessage());
        }
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
        $this->reset(['name', 'description', 'is_active', 'domains', 'syncUsers']);
        $this->resetValidation();
    }

    public function createOrganization()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:organizations,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'domains' => 'nullable|string',
            'syncUsers' => 'boolean',
        ]);

        try {
            $organization = Organization::create([
                'name' => $this->name,
                'description' => $this->description,
                'is_active' => $this->is_active,
            ]);

            // Process domains if provided - dispatch background job
            if (!empty($this->domains)) {
                $domainList = array_map('trim', explode(',', $this->domains));
                $domainList = array_filter($domainList); // Remove empty values

                // Create sync log entry
                $syncLog = \App\Models\SyncLog::create([
                    'type' => 'sync_organization_domains',
                    'status' => 'pending',
                    'user_id' => auth()->id(),
                    'total_items' => 0,
                    'processed_items' => 0,
                    'successful_items' => 0,
                    'failed_items' => 0,
                    'metadata' => [
                        'organization_id' => $organization->id,
                        'organization_name' => $organization->name,
                        'domains' => $domainList,
                    ],
                ]);

                // Dispatch the job to run in the background
                \App\Jobs\SyncOrganizationDomainsJob::dispatch(
                    $syncLog->id,
                    $organization->id,
                    $domainList,
                    $this->syncUsers
                );

                // Start polling for status updates
                $this->activeSyncLogId = $syncLog->id;
                $this->pollingInterval = 2000; // Poll every 2 seconds

                session()->flash('message', "Organization created successfully. Domain sync started in the background (Sync Log ID: #{$syncLog->id}). <a href='" . route('admin.sync-logs.index') . "' class='underline font-bold'>View Progress</a>");
            } else {
                session()->flash('message', 'Organization created successfully.');
            }

            $this->closeCreateModal();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to create organization', [
                'error' => $e->getMessage(),
                'name' => $this->name
            ]);
            session()->flash('error', 'Failed to create organization: ' . $e->getMessage());
        }
    }

    public function openEditModal($id)
    {
        $organization = Organization::with('domains')->findOrFail($id);

        $this->name = $organization->name;
        $this->description = $organization->description;
        $this->is_active = $organization->is_active;

        // Load current domains as comma-separated string
        $this->domains = $organization->domains->pluck('domain')->implode(', ');
        $this->syncUsers = true; // Default to true

        // Store only the ID to avoid serialization issues with JSON columns
        $this->organizationToEdit = $organization->only(['id', 'name']);

        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->organizationToEdit = null;
        $this->reset(['name', 'description', 'is_active', 'domains', 'syncUsers']);
        $this->resetValidation();
    }

    public function updateOrganization()
    {
        $orgId = is_array($this->organizationToEdit) ? $this->organizationToEdit['id'] : $this->organizationToEdit->id;

        $this->validate([
            'name' => 'required|string|max:255|unique:organizations,name,' . $orgId,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'domains' => 'nullable|string',
            'syncUsers' => 'boolean',
        ]);

        try {
            // Reload organization with relationships to avoid serialization issues
            $organization = Organization::with('domains')->findOrFail($orgId);

            $organization->update([
                'name' => $this->name,
                'description' => $this->description,
                'is_active' => $this->is_active,
            ]);

            // Parse domains from comma-separated string
            $domainList = [];
            if (!empty($this->domains)) {
                $domainList = array_map('trim', explode(',', $this->domains));
                $domainList = array_filter($domainList); // Remove empty values
            }

            // Get current domains for comparison
            $currentDomains = $organization->domains->pluck('domain')->toArray();
            sort($currentDomains);
            sort($domainList);

            // Check if domains have changed
            $domainsChanged = $currentDomains !== $domainList;

            // Always sync domains if they changed (including removal of all domains)
            if ($domainsChanged) {
                if (!empty($domainList)) {
                    // Create sync log entry for adding/updating domains
                    $syncLog = \App\Models\SyncLog::create([
                        'type' => 'sync_organization_domains',
                        'status' => 'pending',
                        'user_id' => auth()->id(),
                        'total_items' => 0,
                        'processed_items' => 0,
                        'successful_items' => 0,
                        'failed_items' => 0,
                        'metadata' => [
                            'organization_id' => $organization->id,
                            'organization_name' => $organization->name,
                            'domains' => $domainList,
                            'action' => 'update',
                        ],
                    ]);

                    // Dispatch the job to run in the background
                    \App\Jobs\SyncOrganizationDomainsJob::dispatch(
                        $syncLog->id,
                        $organization->id,
                        $domainList,
                        $this->syncUsers
                    );

                    // Start polling for status updates
                    $this->activeSyncLogId = $syncLog->id;
                    $this->pollingInterval = 2000; // Poll every 2 seconds

                    session()->flash('message', "Organization updated successfully. Domain sync started in the background (Sync Log ID: #{$syncLog->id}). <a href='" . route('admin.sync-logs.index') . "' class='underline font-bold'>View Progress</a>");
                } else {
                    // User cleared all domains - detach all domains from this organization
                    $organization->domains()->detach();

                    \Illuminate\Support\Facades\Log::info('All domains removed from organization', [
                        'organization_id' => $organization->id,
                        'organization_name' => $organization->name,
                        'removed_count' => count($currentDomains)
                    ]);

                    session()->flash('message', 'Organization updated successfully. All domains removed.');
                }
            } else {
                session()->flash('message', 'Organization updated successfully.');
            }

            $this->closeEditModal();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update organization', [
                'error' => $e->getMessage(),
                'organization_id' => $orgId
            ]);
            session()->flash('error', 'Failed to update organization: ' . $e->getMessage());
        }
    }

    public function confirmDelete($id)
    {
        $organization = Organization::findOrFail($id);
        // Store only the ID and name to avoid serialization issues with JSON columns
        $this->organizationToDelete = $organization->only(['id', 'name']);
        $this->showDeleteModal = true;
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->organizationToDelete = null;
    }

    public function deleteOrganization()
    {
        if ($this->organizationToDelete) {
            try {
                $organizationName = is_array($this->organizationToDelete) ? $this->organizationToDelete['name'] : $this->organizationToDelete->name;
                $organizationId = is_array($this->organizationToDelete) ? $this->organizationToDelete['id'] : $this->organizationToDelete->id;

                // Find "Default Organization" for fallback
                $defaultOrganization = Organization::where('name', 'Default Organization')->first();

                // Only proceed with user migration if this is NOT the Default Organization
                if ($defaultOrganization && $organizationId !== $defaultOrganization->id) {
                    // Check if there are users to move
                    $usersCount = \App\Models\User::where('organization_id', $organizationId)->count();

                    if ($usersCount > 0) {
                        // Create sync log entry
                        $syncLog = \App\Models\SyncLog::create([
                            'type' => 'move_users_to_default_organization',
                            'status' => 'pending',
                            'user_id' => auth()->id(),
                            'total_items' => 0,
                            'processed_items' => 0,
                            'successful_items' => 0,
                            'failed_items' => 0,
                            'metadata' => [
                                'organization_id' => $organizationId,
                                'organization_name' => $organizationName,
                                'default_organization_id' => $defaultOrganization->id,
                            ],
                        ]);

                        // Dispatch the job to run in the background
                        \App\Jobs\MoveUsersToDefaultOrganizationJob::dispatch(
                            $syncLog->id,
                            $organizationId,
                            $defaultOrganization->id
                        );

                        // Start polling for status updates
                        $this->activeSyncLogId = $syncLog->id;
                        $this->pollingInterval = 2000; // Poll every 2 seconds

                        \Illuminate\Support\Facades\Log::info('Move users job dispatched before organization deletion', [
                            'organization_id' => $organizationId,
                            'organization_name' => $organizationName,
                            'users_count' => $usersCount,
                            'sync_log_id' => $syncLog->id
                        ]);
                    }
                }

                // Delete the organization immediately (users will be moved in background)
                Organization::findOrFail($organizationId)->delete();

                if (isset($syncLog)) {
                    session()->flash('message', "Organization '{$organizationName}' deleted. User migration started in the background (Sync Log ID: #{$syncLog->id}). <a href='" . route('admin.sync-logs.index') . "' class='underline font-bold'>View Progress</a>");
                } else {
                    session()->flash('message', "Organization '{$organizationName}' deleted successfully.");
                }

                $this->cancelDelete();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to delete organization', [
                    'error' => $e->getMessage(),
                    'organization_id' => $organizationId ?? null
                ]);
                session()->flash('error', 'Failed to delete organization: ' . $e->getMessage());
            }
        }
    }

    public function toggleActive($id)
    {
        $organization = Organization::findOrFail($id);
        $organization->update(['is_active' => !$organization->is_active]);
        session()->flash('message', 'Organization status updated.');
    }

    public function openManageUsersModal($id)
    {
        // PERFORMANCE: Don't eager load users - only store the ID
        $this->organizationToManageId = $id;
        $this->userSearch = '';

        // PERFORMANCE: Don't load ALL user IDs into memory!
        // For organizations with 50k+ users, loading all IDs is still slow
        // Instead, we'll query the database on-demand for each paginated view
        $this->selectedUsers = [];

        $this->showManageUsersModal = true;
    }

    public function closeManageUsersModal()
    {
        $this->showManageUsersModal = false;
        $this->organizationToManageId = null;
        $this->userSearch = '';
        $this->selectedUsers = [];
        $this->userPage = 1;
        $this->assignedUsersPage = 1;
    }

    public function updatingUserSearch()
    {
        $this->userPage = 1;
    }

    public function loadMoreUsers()
    {
        $this->userPage++;
    }

    public function loadMoreAssignedUsers()
    {
        $this->assignedUsersPage++;
    }

    // REMOVED: updateUsers() method
    // For large organizations (50k+ users), we can't track selections in memory
    // Instead, users are added/removed individually using attachUser/detachUser

    #[\Livewire\Attributes\Computed]
    public function organizationToManage()
    {
        if (!$this->organizationToManageId) {
            return null;
        }

        return Organization::findOrFail($this->organizationToManageId);
    }

    #[\Livewire\Attributes\Computed]
    public function filteredUsers()
    {
        if (!$this->organizationToManageId) {
            return collect();
        }

        // Don't run query if no search term - improves initial modal load performance
        if (empty($this->userSearch)) {
            return collect();
        }

        $query = \App\Models\User::query()
            ->with(['domain', 'organizations' => function ($query) {
                $query->where('organizations.id', $this->organizationToManageId)
                    ->select('organizations.id', 'organizations.name')
                    ->withPivot('is_manual', 'is_primary');
            }])
            ->where(function ($query) {
                $query->where('fullname', 'like', '%' . $this->userSearch . '%')
                    ->orWhere('email', 'like', '%' . $this->userSearch . '%');
            })
            ->orderBy('fullname');

        // Use paginate and return items only
        return $query->paginate($this->userPage * $this->usersPerPage)->items();
    }

    #[\Livewire\Attributes\Computed]
    public function totalUsersCount()
    {
        if (!$this->organizationToManageId) {
            return 0;
        }

        // Don't run count query if no search term
        if (empty($this->userSearch)) {
            return 0;
        }

        return \App\Models\User::query()
            ->where(function ($query) {
                $query->where('fullname', 'like', '%' . $this->userSearch . '%')
                    ->orWhere('email', 'like', '%' . $this->userSearch . '%');
            })
            ->count();
    }

    #[\Livewire\Attributes\Computed]
    public function hasMoreUsers()
    {
        $filteredUsers = $this->filteredUsers;
        $count = is_array($filteredUsers) ? count($filteredUsers) : $filteredUsers->count();
        return $count < $this->totalUsersCount;
    }

    #[\Livewire\Attributes\Computed]
    public function assignedUsers()
    {
        if (!$this->organizationToManageId) {
            return collect();
        }

        $organization = Organization::findOrFail($this->organizationToManageId);

        $query = $organization->usersMany()
            ->with('domain')
            ->orderByRaw('CASE WHEN organization_user.is_manual = 1 THEN 0 ELSE 1 END')
            ->orderBy('fullname');

        // Use paginate and return items only
        return $query->paginate($this->assignedUsersPage * $this->assignedUsersPerPage)->items();
    }

    #[\Livewire\Attributes\Computed]
    public function totalAssignedUsers()
    {
        if (!$this->organizationToManageId) {
            return 0;
        }

        $organization = Organization::findOrFail($this->organizationToManageId);
        return $organization->usersMany()->count();
    }

    #[\Livewire\Attributes\Computed]
    public function hasMoreAssignedUsers()
    {
        $assignedUsers = $this->assignedUsers;
        $count = is_array($assignedUsers) ? count($assignedUsers) : count($assignedUsers);
        return $count < $this->totalAssignedUsers;
    }

    public function attachUser($userId, $isManual = true)
    {
        try {
            $organization = Organization::findOrFail($this->organizationToManageId);

            // Check if user is already attached
            $exists = \Illuminate\Support\Facades\DB::table('organization_user')
                ->where('organization_id', $organization->id)
                ->where('user_id', $userId)
                ->exists();

            if ($exists) {
                session()->flash('error', 'User is already assigned to this organization.');
                return;
            }

            // Attach user with is_manual flag
            $organization->usersMany()->attach($userId, ['is_manual' => $isManual]);

            session()->flash('message', 'User added successfully.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to attach user', [
                'error' => $e->getMessage(),
                'organization_id' => $this->organizationToManageId,
                'user_id' => $userId
            ]);
            session()->flash('error', 'Failed to add user: ' . $e->getMessage());
        }
    }

    public function detachUser($userId)
    {
        try {
            $organization = Organization::findOrFail($this->organizationToManageId);
            $organization->usersMany()->detach($userId);

            session()->flash('message', 'User removed successfully.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to detach user', [
                'error' => $e->getMessage(),
                'organization_id' => $this->organizationToManageId,
                'user_id' => $userId
            ]);
            session()->flash('error', 'Failed to remove user: ' . $e->getMessage());
        }
    }
}
