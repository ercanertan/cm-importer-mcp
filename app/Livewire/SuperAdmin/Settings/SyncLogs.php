<?php

namespace App\Livewire\SuperAdmin\Settings;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.super-admin')]
#[Title('Sync Logs')]
class SyncLogs extends Component
{
    public function render()
    {
        return view('livewire.super-admin.settings.sync-logs');
    }
}
