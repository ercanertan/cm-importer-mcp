<?php

namespace App\Livewire\SuperAdmin\Organizations;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.super-admin')]
#[Title('Organization Management')]
class OrganizationManager extends Component
{
    public function render()
    {
        return view('livewire.super-admin.organizations.organization-manager');
    }
}
