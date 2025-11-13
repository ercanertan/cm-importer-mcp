<?php

namespace App\Livewire\SuperAdmin\Domains;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.super-admin')]
#[Title('Domain Management')]
class DomainManager extends Component
{
    public function render()
    {
        return view('livewire.super-admin.domains.domain-manager');
    }
}
