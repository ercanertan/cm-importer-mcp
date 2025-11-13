<?php

namespace App\Livewire\SuperAdmin\Cdp;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.super-admin')]
#[Title('Template Management')]
class TemplateManager extends Component
{
    public function render()
    {
        return view('livewire.super-admin.cdp.template-manager');
    }
}
