<?php

namespace App\Livewire\SuperAdmin\Users;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.super-admin')]
#[Title('User Role Assignment')]
class UserRoleAssignment extends Component
{
    public function render()
    {
        return view('livewire.super-admin.users.user-role-assignment');
    }
}
