<?php

namespace App\Livewire\User\Subscriptions;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.user')]
#[Title('Subscription Preferences')]
class SubscriptionPreferences extends Component
{
    public function render()
    {
        return view('livewire.user.subscriptions.subscription-preferences');
    }
}
