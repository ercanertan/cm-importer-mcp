<?php

namespace App\Livewire\UserProfile;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class PrimaryOrganizationManager extends Component
{
    public $organizations = [];
    public $primaryOrganizationId;

    public function mount()
    {
        $this->loadOrganizations();
    }

    public function loadOrganizations()
    {
        $user = Auth::user();

        // Load all organizations the user belongs to
        $this->organizations = $user->organizations()
            ->get()
            ->map(function ($org) {
                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'is_primary' => $org->pivot->is_primary,
                    'is_manual' => $org->pivot->is_manual,
                ];
            });

        // Get the current primary organization ID
        $primaryOrg = $this->organizations->firstWhere('is_primary', true);
        $this->primaryOrganizationId = $primaryOrg['id'] ?? null;
    }

    public function setPrimary($organizationId)
    {
        $user = Auth::user();

        // Attempt to set the primary organization
        if ($user->setPrimaryOrganization($organizationId)) {
            // Reload organizations to reflect the change
            $this->loadOrganizations();

            session()->flash('message', 'Primary organization updated successfully.');
        } else {
            session()->flash('error', 'Failed to update primary organization.');
        }
    }

    public function render()
    {
        return view('livewire.user-profile.primary-organization-manager');
    }
}
