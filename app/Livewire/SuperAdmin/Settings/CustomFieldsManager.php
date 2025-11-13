<?php

namespace App\Livewire\SuperAdmin\Settings;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.super-admin')]
#[Title('Custom Fields')]
class CustomFieldsManager extends Component
{
    public function render()
    {
        return view('livewire.super-admin.settings.custom-fields-manager');
    }
}
