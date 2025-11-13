<?php

namespace App\Livewire\User\Profile;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.user')]
#[Title('Edit Profile')]
class EditProfile extends Component
{
    public function render()
    {
        return view('livewire.user.profile.edit-profile');
    }
}
