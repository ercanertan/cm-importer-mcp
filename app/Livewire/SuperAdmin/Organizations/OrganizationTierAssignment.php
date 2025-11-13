<?php

namespace App\Livewire\SuperAdmin\Organizations;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.super-admin')]
#[Title('Tier Assignment')]
class OrganizationTierAssignment extends Component
{
    public function render()
    {
        return view('livewire.super-admin.organizations.organization-tier-assignment');
    }
}
