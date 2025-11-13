<?php

namespace App\Livewire\OrgAdmin\Products;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.org-admin')]
#[Title('Product Subscriptions')]
class ProductSubscriptions extends Component
{
    public function render()
    {
        return view('livewire.org-admin.products.product-subscriptions');
    }
}
