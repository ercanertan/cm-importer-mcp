<?php

namespace App\Livewire\OrgAdmin\Products;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.org-admin')]
#[Title('Bulk Subscribe')]
class BulkSubscribe extends Component
{
    public function render()
    {
        return view('livewire.org-admin.products.bulk-subscribe');
    }
}
