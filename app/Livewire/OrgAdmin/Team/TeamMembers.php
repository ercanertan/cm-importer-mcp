<?php

namespace App\Livewire\OrgAdmin\Team;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.org-admin')]
#[Title('Team Members')]
class TeamMembers extends Component
{
    public function render()
    {
        return view('livewire.org-admin.team.team-members');
    }
}
