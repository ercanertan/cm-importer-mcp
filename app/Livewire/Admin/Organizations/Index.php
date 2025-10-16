<?php

namespace App\Livewire\Admin\Organizations;

use App\Models\Organization;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $showDeleteModal = false;
    public $organizationToDelete = null;
    public $showCreateModal = false;
    public $showEditModal = false;
    public $organizationToEdit = null;

    // Form fields
    public $name = '';
    public $description = '';
    public $is_active = true;

    protected $queryString = ['search'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $organizations = Organization::query()
            ->withCount(['users', 'domains'])
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.organizations.index', [
            'organizations' => $organizations
        ])->layout('components.layouts.app', ['title' => 'Organizations']);
    }

    public function openCreateModal()
    {
        $this->reset(['name', 'description', 'is_active']);
        $this->is_active = true;
        $this->showCreateModal = true;
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
        $this->reset(['name', 'description', 'is_active']);
        $this->resetValidation();
    }

    public function createOrganization()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:organizations,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        Organization::create([
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
        ]);

        session()->flash('message', 'Organization created successfully.');
        $this->closeCreateModal();
    }

    public function openEditModal($id)
    {
        $organization = Organization::findOrFail($id);
        $this->organizationToEdit = $organization;
        $this->name = $organization->name;
        $this->description = $organization->description;
        $this->is_active = $organization->is_active;
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->organizationToEdit = null;
        $this->reset(['name', 'description', 'is_active']);
        $this->resetValidation();
    }

    public function updateOrganization()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:organizations,name,' . $this->organizationToEdit->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $this->organizationToEdit->update([
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
        ]);

        session()->flash('message', 'Organization updated successfully.');
        $this->closeEditModal();
    }

    public function confirmDelete($id)
    {
        $this->organizationToDelete = Organization::findOrFail($id);
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
            $this->organizationToDelete->delete();
            session()->flash('message', 'Organization deleted successfully.');
            $this->cancelDelete();
        }
    }

    public function toggleActive($id)
    {
        $organization = Organization::findOrFail($id);
        $organization->update(['is_active' => !$organization->is_active]);
        session()->flash('message', 'Organization status updated.');
    }
}
