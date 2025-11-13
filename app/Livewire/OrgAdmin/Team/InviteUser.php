<?php

namespace App\Livewire\OrgAdmin\Team;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.org-admin')]
#[Title('Invite User')]
class InviteUser extends Component
{
    public function render()
    {
        return view('livewire.org-admin.team.invite-user');
    }
}
