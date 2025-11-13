<?php

namespace App\Livewire\OrgAdmin\Settings;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.org-admin')]
#[Title('Organization Settings')]
class OrganizationSettings extends Component
{
    public function render()
    {
        return view('livewire.org-admin.settings.organization-settings');
    }
}
