<?php

namespace App\Livewire\User\Activity;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.user')]
#[Title('Email Activity')]
class ActivityHistory extends Component
{
    public function render()
    {
        return view('livewire.user.activity.activity-history');
    }
}
