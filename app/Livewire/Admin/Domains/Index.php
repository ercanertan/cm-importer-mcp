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
    public $syncingAll = false;

    // Form fields
    public $domain = '';
    public $organization_id = null;
    public $selectedOrganizations = [];

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
            ->orderBy('user_count', 'desc')
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
                'domain' => strtolower(trim($this->domain)),
                'user_count' => 0
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
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->domainToEdit = null;
        $this->reset(['domain', 'selectedOrganizations']);
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

            // Sync organizations
            $this->domainToEdit->organizations()->sync($this->selectedOrganizations);

            session()->flash('message', 'Domain updated successfully.');
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
        $this->syncingAll = true;

        try {
            $domains = Domain::all();
            $totalAssigned = 0;

            foreach ($domains as $domain) {
                $result = $domain->assignUsersFromDomain();
                $totalAssigned += $result['assigned_count'];
            }

            Log::info('All domains synced', [
                'total_domains' => $domains->count(),
                'total_assigned' => $totalAssigned
            ]);

            session()->flash('message', "Synced {$domains->count()} domains. {$totalAssigned} users updated.");
        } catch (\Exception $e) {
            Log::error('Failed to sync all domains', [
                'error' => $e->getMessage()
            ]);
            session()->flash('error', 'Failed to sync domains: ' . $e->getMessage());
        } finally {
            $this->syncingAll = false;
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
            $this->domainToAssociate->organizations()->sync($this->selectedOrganizations);

            // Trigger user assignment for newly associated organizations
            if (!empty($this->selectedOrganizations)) {
                $result = $this->domainToAssociate->assignUsersFromDomain();
                session()->flash('message', "Organizations updated. {$result['assigned_count']} users reassigned.");
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
