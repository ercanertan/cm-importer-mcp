<?php

namespace App\Livewire\User\Profile;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.user')]
#[Title('Privacy & Consent')]
class ManageConsent extends Component
{
    public function render()
    {
        return view('livewire.user.profile.manage-consent');
    }
}
