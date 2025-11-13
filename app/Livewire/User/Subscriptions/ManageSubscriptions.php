<?php

namespace App\Livewire\User\Subscriptions;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.user')]
#[Title('Manage Subscriptions')]
class ManageSubscriptions extends Component
{
    public function render()
    {
        return view('livewire.user.subscriptions.manage-subscriptions');
    }
}
